<?php

namespace App\Queries;

use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\StockHolder;
use App\Models\User;
use App\Services\StockMovementService;
use App\Support\SqlLike;

class CheckInEntitlementsQuery
{
    public function dataTable(Event $event, array $data, User $viewer): array
    {
        $query = ExpectedEntitlement::query()
            ->join('pass_assignments as pa', 'pa.id', '=', 'expected_entitlements.pass_assignment_id')
            ->join('pass_types as pt', 'pt.id', '=', 'pa.pass_type_id')
            ->join('entitlement_items as ei', 'ei.id', '=', 'expected_entitlements.entitlement_item_id')
            ->leftJoin('issued_entitlements as ie', 'ie.expected_entitlement_id', '=', 'expected_entitlements.id')
            ->leftJoin('stock_movements as sm', 'sm.issued_entitlement_id', '=', 'ie.id')
            ->leftJoin('stock_holders as giver_holder', 'giver_holder.id', '=', 'sm.from_holder_id')
            ->leftJoin('people as giver', 'giver.id', '=', 'giver_holder.person_id')
            ->leftJoin('locations as giver_location', 'giver_location.id', '=', 'giver_holder.location_id')
            ->leftJoin('people as receiver', 'receiver.id', '=', 'pa.person_id')
            ->leftJoin('users as recorder', 'recorder.id', '=', 'sm.actor_user_id')
            ->where('pa.'.$data['type'].'_engagement_id', $data['engagement_id'])
            ->where('pa.person_id', $data['person_id'])
            ->where('pt.event_id', $event->id)->where('ei.event_id', $event->id)
            ->select('expected_entitlements.*');
        $total = (clone $query)->count();
        $search = trim((string) data_get($data, 'search.value', ''));
        if ($search !== '') {
            $pattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';
            $query->where(function ($query) use ($pattern): void {
                $query->whereRaw("LOWER(ei.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(ie.code) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(COALESCE(giver.name, giver_location.name, '')) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(receiver.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(recorder.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("CASE WHEN ie.id IS NULL THEN 'pending' ELSE 'issued' END LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        $filtered = (clone $query)->count();
        $direction = data_get($data, 'order.0.dir') === 'desc' ? 'desc' : 'asc';
        $column = (int) data_get($data, 'order.0.column', 0);
        $sort = match ($column) {
            1 => "CASE WHEN ie.id IS NULL THEN 'pending' ELSE 'issued' END",
            2 => "LOWER(COALESCE(ie.code, ''))",
            3 => "LOWER(COALESCE(giver.name, giver_location.name, ''))",
            4 => "CASE WHEN ie.id IS NULL THEN '' ELSE LOWER(COALESCE(receiver.name, '')) END",
            5 => "LOWER(COALESCE(recorder.name, ''))",
            6 => 'ie.issued_at',
            default => 'LOWER(ei.name)',
        };
        $query->orderByRaw("{$sort} {$direction}")->orderBy('expected_entitlements.id');
        if ((int) $data['length'] !== -1) {
            $query->offset((int) $data['start'])->limit((int) $data['length']);
        }
        $rows = $query
            ->with([
                'passAssignment.passType:id,name', 'passAssignment.person:id,name',
                'issuedEntitlement.issuedBy:id,name',
                'issuedEntitlement.giveOutMovement.fromHolder.person:id,name',
                'issuedEntitlement.giveOutMovement.fromHolder.location:id,name',
                'issuedEntitlement.giveOutMovement.actor:id,name',
                'entitlementItem' => fn ($items) => $items->select('id', 'name', 'needs_unique_code')->withEventLabels($event->id),
            ])->get();

        $holderId = $viewer->person_id === null ? null
            : StockHolder::where('event_id', $event->id)->where('person_id', $viewer->person_id)->value('id');
        $balances = $holderId === null ? collect() : app(StockMovementService::class)->balancesQuery($event->id)
            ->where('holder_id', $holderId)->whereIn('entitlement_item_id', $rows->pluck('entitlement_item_id')->unique())
            ->pluck('balance', 'entitlement_item_id');
        $permissions = app(InventoryTargetsQuery::class)->permissions($event, $viewer);
        $canConsume = in_array('checkin.edit', $permissions, true);
        $inactive = ! $canConsume && ! in_array('inventory.manage', $permissions, true);
        foreach ($rows as $row) {
            $balance = (int) $balances->get($row->entitlement_item_id, 0);
            $reason = match (true) {
                $event->isLocked() => __('artists.check_in.locked'),
                $inactive && $balance > 0 => __('check_in.inactive_stock'),
                ! $canConsume => __('check_in.needs_edit_permission'),
                $balance < 1 => __('check_in.no_stock'),
                default => null,
            };
            $row->setAttribute('own_stock', $balance);
            $row->setAttribute('can_consume', $reason === null && $row->issuedEntitlement === null);
            $row->setAttribute('consume_blocked_reason', $reason);
        }

        return compact('total', 'filtered', 'rows');
    }
}
