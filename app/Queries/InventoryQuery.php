<?php

namespace App\Queries;

use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\StockHolder;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use App\Support\EntitlementLabels;
use App\Support\SqlLike;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryQuery
{
    public function __construct(private readonly StockMovementService $stock, private readonly InventoryTargetsQuery $targets) {}

    /** @return \Illuminate\Database\Eloquent\Collection<int, EntitlementItem> */
    public function itemOptions(Event $event): \Illuminate\Database\Eloquent\Collection
    {
        return $event->entitlementItems()->withEventLabels($event->id)
            ->orderBy('name')->get(['id', 'name', 'low_stock_level']);
    }

    public function items(int $eventId, int $holderId): Builder
    {
        return DB::query()->fromSub($this->stock->balancesQuery($eventId), 'b')
            ->join('entitlement_items as i', 'i.id', '=', 'b.entitlement_item_id')
            ->where('b.holder_id', $holderId)->where('b.balance', '>', 0)
            ->select('i.id', 'i.name', 'b.balance')->orderBy('i.name')->orderBy('i.id');
    }

    public function holdings(Event $event, array $filters = []): Builder
    {
        $totals = $this->stock->balancesQuery($event->id);
        $query = DB::table('stock_holders as sh')->where('sh.event_id', $event->id)
            ->joinSub($totals, 'b', 'b.holder_id', '=', 'sh.id')
            ->leftJoin('locations as l', 'l.id', '=', 'sh.location_id')
            ->leftJoin('people as p', 'p.id', '=', 'sh.person_id')
            ->select('sh.id', 'sh.person_id', 'sh.location_id')
            ->selectRaw('COALESCE(l.name, p.name) as name, SUM(b.balance) as total')
            ->groupBy('sh.id', 'sh.person_id', 'sh.location_id', 'l.name', 'p.name')
            ->havingRaw('SUM(b.balance) > 0');
        if (! empty($filters['item'])) {
            $query->whereExists(function (Builder $matching) use ($event, $filters): void {
                $matching->selectRaw('1')->fromSub($this->stock->balancesQuery($event->id), 'matching')
                    ->whereColumn('matching.holder_id', 'sh.id')
                    ->where('matching.entitlement_item_id', $filters['item'])->where('matching.balance', '>', 0);
            });
        }
        if (($filters['holder_type'] ?? '') === 'location') {
            $query->whereNotNull('sh.location_id');
        } elseif (($filters['holder_type'] ?? '') === 'person') {
            $query->whereNotNull('sh.person_id');
        }
        if (filled($filters['search'] ?? null)) {
            $query->whereNotNull('sh.person_id')->whereRaw("LOWER(p.name) LIKE ? ESCAPE '!'", [
                '%'.SqlLike::escape(mb_strtolower(trim($filters['search']))).'%']);
        }

        return $query->orderByRaw('CASE WHEN sh.location_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByRaw('LOWER(COALESCE(l.name, p.name))')->orderBy('sh.id');
    }

    public function holdingRows(Event $event, array $rows): array
    {
        $ids = array_column($rows, 'id');
        $balances = DB::query()->fromSub($this->stock->balancesQuery($event->id), 'b')
            ->join('entitlement_items as i', 'i.id', '=', 'b.entitlement_item_id')
            ->whereIn('b.holder_id', $ids)->where('b.balance', '>', 0)
            ->select('b.holder_id', 'i.id', 'i.name', 'b.balance')->orderBy('i.name')->get()->groupBy('holder_id');
        $labels = EntitlementLabels::forItems($event->id, $balances->flatten(1)->pluck('id'));
        $eligible = $this->targets->receivers($event)->whereIn('p.id', array_column($rows, 'person_id'))->pluck('p.id')->all();

        return array_map(fn ($row) => [
            'id' => (int) $row->id, 'name' => $row->name,
            'holder_type' => $row->location_id !== null ? 'location' : 'person',
            'person_id' => $row->person_id === null ? null : (int) $row->person_id,
            'total' => (int) $row->total,
            'inactive' => $row->person_id !== null && ! in_array($row->person_id, $eligible),
            'items' => ($balances[$row->id] ?? collect())->map(fn ($item) => [
                'id' => (int) $item->id, 'name' => $item->name, 'balance' => (int) $item->balance,
                'labels' => $labels->get($item->id, []),
            ])->values()->all(),
        ], $rows);
    }

    public function itemRows(int $eventId, Collection $rows): Collection
    {
        $labels = EntitlementLabels::forItems($eventId, $rows->pluck('id'));

        return $rows->map(fn ($row): array => [
            'id' => (int) $row->id, 'name' => $row->name, 'balance' => (int) $row->balance,
            'labels' => $labels->get($row->id, []),
        ]);
    }

    public function peopleHoldingItem(EntitlementItem $item): Builder
    {
        return DB::query()->fromSub($this->stock->balancesQuery($item->event_id), 'b')
            ->join('stock_holders as h', 'h.id', '=', 'b.holder_id')
            ->join('people as p', 'p.id', '=', 'h.person_id')
            ->where('h.event_id', $item->event_id)->where('b.entitlement_item_id', $item->id)
            ->where('b.balance', '>', 0)->select('p.id', 'p.name', 'b.balance')
            ->orderByRaw('LOWER(p.name)')->orderBy('p.id');
    }

    public function peopleHoldingItemRows(Event $event, Collection $rows): Collection
    {
        $eligible = $this->targets->receivers($event)->whereIn('p.id', $rows->pluck('id'))->pluck('p.id')->all();

        return $rows->map(fn ($row) => [
            'id' => (int) $row->id, 'name' => $row->name, 'balance' => (int) $row->balance,
            'inactive' => ! in_array($row->id, $eligible),
        ]);
    }

    public function movements(StockHolder $holder): \Illuminate\Database\Eloquent\Builder
    {
        return StockMovement::with(['item' => fn ($items) => $items->select('id', 'name')->withEventLabels($holder->event_id), 'fromHolder.person:id,name', 'fromHolder.location:id,name',
            'toHolder.person:id,name', 'toHolder.location:id,name', 'actor:id,name',
            'issuedEntitlement.expectedEntitlement.passAssignment.person:id,name'])
            ->where('event_id', $holder->event_id)
            ->where(fn ($q) => $q->where('from_holder_id', $holder->id)->orWhere('to_holder_id', $holder->id))
            ->orderByDesc('created_at')->orderByDesc('id');
    }
}
