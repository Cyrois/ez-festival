<?php

namespace App\Queries;

use App\Models\Event;
use App\Models\StockMovement;
use App\Support\SqlLike;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class InventoryHistoryQuery
{
    public function movements(Event $event, ?int $itemId = null, array $filters = []): Builder
    {
        $query = StockMovement::query()->where('event_id', $event->id)
            ->when($itemId !== null, fn (Builder $q) => $q->where('entitlement_item_id', $itemId));

        if (filled($filters['item_id'] ?? null)) {
            $query->where('entitlement_item_id', $filters['item_id']);
        }
        if (filled($filters['recorded_by'] ?? null)) {
            $query->where('actor_user_id', $filters['recorded_by']);
        }
        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }
        if (filled($filters['holder'] ?? null)) {
            [$kind, $id] = explode(':', $filters['holder'], 2);
            $query->where(function (Builder $q) use ($kind, $id): void {
                $column = $kind === 'person' ? 'person_id' : 'location_id';
                $q->whereHas('fromHolder', fn (Builder $h) => $h->where($column, $id))
                    ->orWhereHas('toHolder', fn (Builder $h) => $h->where($column, $id));
                if ($kind === 'person') {
                    $q->orWhereHas('issuedEntitlement.expectedEntitlement.passAssignment', fn (Builder $p) => $p->where('person_id', $id));
                }
            });
        }
        if (filled($filters['start_date'] ?? null)) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['start_date'], $event->timezone)->startOfDay()->utc())
                ->where('created_at', '<', CarbonImmutable::parse($filters['end_date'], $event->timezone)->addDay()->startOfDay()->utc());
        }

        return $query;
    }

    public function ordered(Builder $query, Event $event): Builder
    {
        return $query->with([
            'item' => fn ($items) => $items->select('id', 'name')->withEventLabels($event->id),
            'fromHolder.person:id,name', 'fromHolder.location:id,name',
            'toHolder.person:id,name', 'toHolder.location:id,name', 'actor:id,name',
            'issuedEntitlement.expectedEntitlement.passAssignment.person:id,name',
        ])->orderByDesc('created_at')->orderByDesc('id');
    }

    /** Historical participants include checked-in people who never held stock. */
    public function holders(Event $event, ?int $itemId = null): QueryBuilder
    {
        $holders = DB::table('stock_movements as m')
            ->join('stock_holders as h', fn ($join) => $join->on('h.id', '=', 'm.from_holder_id')->orOn('h.id', '=', 'm.to_holder_id'))
            ->leftJoin('people as p', 'p.id', '=', 'h.person_id')
            ->leftJoin('locations as l', 'l.id', '=', 'h.location_id')
            ->where('m.event_id', $event->id)->where('h.event_id', $event->id)
            ->when($itemId !== null, fn ($q) => $q->where('m.entitlement_item_id', $itemId))
            ->selectRaw("CASE WHEN h.person_id IS NOT NULL THEN 'person:' || CAST(h.person_id AS TEXT) ELSE 'location:' || CAST(h.location_id AS TEXT) END as value")
            ->selectRaw("COALESCE(p.name, l.name) as name, CASE WHEN h.person_id IS NOT NULL THEN 'person' ELSE 'location' END as kind");
        $recipients = DB::table('stock_movements as m')
            ->join('issued_entitlements as i', 'i.id', '=', 'm.issued_entitlement_id')
            ->join('expected_entitlements as e', 'e.id', '=', 'i.expected_entitlement_id')
            ->join('pass_assignments as a', 'a.id', '=', 'e.pass_assignment_id')
            ->join('people as p', 'p.id', '=', 'a.person_id')
            ->where('m.event_id', $event->id)
            ->when($itemId !== null, fn ($q) => $q->where('m.entitlement_item_id', $itemId))
            ->selectRaw("'person:' || CAST(p.id AS TEXT) as value, p.name, 'person' as kind");

        return DB::query()->fromSub($holders->union($recipients), 'participants');
    }

    public function actors(Event $event, ?int $itemId = null): QueryBuilder
    {
        return DB::query()->fromSub(DB::table('stock_movements as m')
            ->join('users as u', 'u.id', '=', 'm.actor_user_id')->where('m.event_id', $event->id)
            ->when($itemId !== null, fn ($q) => $q->where('m.entitlement_item_id', $itemId))
            ->selectRaw("CAST(u.id AS TEXT) as value, u.name, 'actor' as kind")->distinct(), 'actors');
    }

    public function options(Event $event, ?int $itemId, string $option, string $search): QueryBuilder
    {
        $query = $option === 'holder' ? $this->holders($event, $itemId) : $this->actors($event, $itemId);

        return $query->when(trim($search) !== '', fn ($q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [
            '%'.SqlLike::escape(mb_strtolower(trim($search))).'%',
        ]))->orderByRaw('LOWER(name)')->orderBy('value');
    }
}
