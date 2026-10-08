<?php

namespace App\Queries;

use App\Models\Event;
use App\Models\TeamEngagement;
use App\Support\SqlLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class KitchenPeopleQuery
{
    public function search(Event $event, string $search, int $page): array
    {
        $query = TeamEngagement::query()->where('event_id', $event->id)->where('status', 'hired');
        $pattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';
        $query->where(fn (Builder $people) => $people
            ->whereHas('person', fn (Builder $names) => $names->whereRaw("lower(name) like ? escape '!'", [$pattern]))
            ->orWhereExists(fn (QueryBuilder $codes) => $this->codes($codes, $event)->whereRaw("lower(ie.code) like ? escape '!'", [$pattern])));
        // Determine a unique exact barcode across the entire scope, never just the current page.
        $exact = (clone $query)->whereExists(fn (QueryBuilder $codes) => $this->codes($codes, $event)
            ->whereRaw('lower(ie.code) = ?', [mb_strtolower($search)]))->limit(2)->pluck('id');
        $people = $query->with('person:id,name')->orderBy('id')->paginate(25, ['*'], 'page', $page);
        $codes = DB::table('pass_assignments as pa')->join('pass_types as pt', 'pt.id', '=', 'pa.pass_type_id')
            ->join('expected_entitlements as ee', 'ee.pass_assignment_id', '=', 'pa.id')
            ->join('issued_entitlements as ie', 'ie.expected_entitlement_id', '=', 'ee.id')
            ->where('pt.event_id', $event->id)->whereIn('pa.person_id', $people->pluck('person_id'))
            ->whereNotNull('ie.code')->where('ie.code', '<>', '')->orderBy('ie.code')
            ->get(['pa.person_id', 'ie.code'])->groupBy('person_id');

        return [
            'people' => $people->map(fn ($member) => [
                'id' => $member->id, 'name' => $member->person->name,
                'type' => __('team.advancement.employment_type.'.$member->employment_type),
                'codes' => $codes->get($member->person_id, collect())->pluck('code')->unique()->values()->all(),
            ])->all(),
            'total' => $people->total(), 'page' => $people->currentPage(), 'last_page' => $people->lastPage(),
            'matched_id' => $exact->count() === 1 ? (int) $exact->first() : null,
        ];
    }

    private function codes(QueryBuilder $query, Event $event): QueryBuilder
    {
        return $query->selectRaw('1')->from('pass_assignments as pa')
            ->join('pass_types as pt', 'pt.id', '=', 'pa.pass_type_id')
            ->join('expected_entitlements as ee', 'ee.pass_assignment_id', '=', 'pa.id')
            ->join('issued_entitlements as ie', 'ie.expected_entitlement_id', '=', 'ee.id')
            ->whereColumn('pa.person_id', 'team_engagements.person_id')->where('pt.event_id', $event->id);
    }
}
