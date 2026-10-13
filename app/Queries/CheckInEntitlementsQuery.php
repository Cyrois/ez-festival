<?php

namespace App\Queries;

use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Support\SqlLike;

class CheckInEntitlementsQuery
{
    public function dataTable(Event $event, array $data): array
    {
        $query = ExpectedEntitlement::query()
            ->join('pass_assignments as pa', 'pa.id', '=', 'expected_entitlements.pass_assignment_id')
            ->join('pass_types as pt', 'pt.id', '=', 'pa.pass_type_id')
            ->join('entitlement_items as ei', 'ei.id', '=', 'expected_entitlements.entitlement_item_id')
            ->leftJoin('issued_entitlements as ie', 'ie.expected_entitlement_id', '=', 'expected_entitlements.id')
            ->leftJoin('users as issuer', 'issuer.id', '=', 'ie.issued_by')
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
                    ->orWhereRaw("LOWER(issuer.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("CASE WHEN ie.id IS NULL THEN 'pending' ELSE 'issued' END LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        $filtered = (clone $query)->count();
        $direction = data_get($data, 'order.0.dir') === 'desc' ? 'desc' : 'asc';
        $column = (int) data_get($data, 'order.0.column', 0);
        $sort = match ($column) {
            1 => "CASE WHEN ie.id IS NULL THEN 'pending' ELSE 'issued' END",
            2 => "LOWER(COALESCE(ie.code, ''))",
            3 => "LOWER(COALESCE(issuer.name, ''))",
            4 => 'ie.issued_at',
            default => 'LOWER(ei.name)',
        };
        $query->orderByRaw("{$sort} {$direction}")->orderBy('expected_entitlements.id');
        if ((int) $data['length'] !== -1) {
            $query->offset((int) $data['start'])->limit((int) $data['length']);
        }
        $rows = $query
            ->with([
                'passAssignment.passType:id,name', 'issuedEntitlement.location:id,name', 'issuedEntitlement.issuedBy:id,name',
                'entitlementItem.adjustments' => fn ($query) => $query->select('entitlement_item_id', 'location_id')
                    ->selectRaw('SUM(delta) as balance')->whereNotNull('location_id')
                    ->groupBy('entitlement_item_id', 'location_id')->havingRaw('SUM(delta) > 0')->with('location:id,name'),
            ])->get();

        return compact('total', 'filtered', 'rows');
    }
}
