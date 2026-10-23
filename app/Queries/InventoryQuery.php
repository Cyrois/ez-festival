<?php

namespace App\Queries;

use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\StockHolder;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use App\Support\EntitlementLabels;
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
