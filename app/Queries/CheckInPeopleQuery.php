<?php

namespace App\Queries;

use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckInPeopleQuery
{
    /**
     * @return LengthAwarePaginator<int, object>
     */
    public function paginate(
        int $eventId,
        ?int $passId,
        string $search,
        string $status,
        string $type,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = $this->baseQuery($eventId, $passId, $search, $status, $type)
            ->orderByRaw('lower(person_name)')->orderBy('type')->orderBy('engagement_id')->orderBy('person_id');

        /** @var LengthAwarePaginator<int, object> $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        return $paginator;
    }

    /**
     * @return LengthAwarePaginator<int, object>
     */
    public function empty(int $perPage = 25): LengthAwarePaginator
    {
        return new Paginator([], 0, $perPage, 1, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);
    }

    /** @return array{total: int, filtered: int, people: Collection} */
    public function dataTable(int $eventId, ?int $passId, string $search, string $status, string $type, int $start, int $length): array
    {
        $total = DB::query()->fromSub($this->baseQuery($eventId, null, '', 'all', 'all'), 'holders')->count();
        if (! in_array($type, ['all', 'artist', 'vendor', 'team'], true)) {
            return ['total' => $total, 'filtered' => 0, 'people' => collect()];
        }

        $query = $this->baseQuery($eventId, $passId, $search, $status, $type);
        $filtered = DB::query()->fromSub(clone $query, 'holders')->count();
        $people = $query->orderByRaw('lower(person_name)')
            ->orderBy('type')->orderBy('engagement_id')->orderBy('person_id')
            ->offset($start)->limit($length)->get();

        return ['total' => $total, 'filtered' => $filtered, 'people' => $people];
    }

    private function baseQuery(int $eventId, ?int $passId, string $search, string $status, string $type): Builder
    {
        return DB::query()->fromSub(
            $this->engagementQuery($eventId, $passId, $search, $status, $type)
                ->unionAll($this->teamQuery($eventId, $passId, $search, $status, $type)),
            'holders',
        );
    }

    private function engagementQuery(int $eventId, ?int $passId, string $search, string $status, string $type): Builder
    {
        $passNames = match (DB::connection()->getDriverName()) {
            'pgsql' => "STRING_AGG(DISTINCT pt.name, ',' ORDER BY pt.name)",
            'sqlite' => 'GROUP_CONCAT(DISTINCT pt.name)',
            default => throw new \LogicException('Check-in requires PostgreSQL or the SQLite test database.'),
        };

        $query = DB::table('pass_assignments as pa')
            ->leftJoin('artist_engagements as ae', function ($join) use ($eventId): void {
                $join->on('ae.id', '=', 'pa.artist_engagement_id')
                    ->where('ae.event_id', '=', $eventId)
                    ->where('ae.status', '=', 'confirmed');
            })
            ->leftJoin('vendor_engagements as ve', function ($join) use ($eventId): void {
                $join->on('ve.id', '=', 'pa.vendor_engagement_id')
                    ->where('ve.event_id', '=', $eventId)
                    ->where('ve.status', '=', 'confirmed');
            })
            ->join('people as p', 'p.id', '=', 'pa.person_id')
            ->leftJoin('artists as a', 'a.id', '=', 'ae.artist_id')
            ->leftJoin('vendors as v', 'v.id', '=', 've.vendor_id')
            ->leftJoin('pass_types as pt', 'pt.id', '=', 'pa.pass_type_id')
            ->leftJoin('expected_entitlements as ee', 'ee.pass_assignment_id', '=', 'pa.id')
            ->leftJoin('issued_entitlements as ie', 'ie.expected_entitlement_id', '=', 'ee.id')
            ->whereNotNull('pa.person_id')
            ->when(! in_array($type, ['all', 'artist', 'vendor'], true), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where(fn (Builder $query) => $query->whereNotNull('ae.id')->orWhereNotNull('ve.id'))
            ->when($type === 'artist', fn (Builder $query) => $query->whereNotNull('ae.id'))
            ->when($type === 'vendor', fn (Builder $query) => $query->whereNotNull('ve.id'))
            ->when($passId !== null, fn (Builder $query) => $query->where('pa.pass_type_id', $passId))
            ->when($search !== '', function (Builder $query) use ($search, $passId): void {
                $pattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';
                $query->where(function (Builder $query) use ($pattern, $passId): void {
                    $query->whereRaw("lower(p.name) like ? escape '!'", [$pattern])
                        ->orWhereRaw("lower(p.email) like ? escape '!'", [$pattern])
                        ->orWhereRaw("lower(a.name) like ? escape '!'", [$pattern])
                        ->orWhereRaw("lower(v.name) like ? escape '!'", [$pattern])
                        ->orWhereExists(function (Builder $codes) use ($pattern, $passId): void {
                            // Search holders without filtering rows from their aggregate ledger.
                            $codes->selectRaw('1')
                                ->from('pass_assignments as search_pa')
                                ->join('expected_entitlements as search_ee', 'search_ee.pass_assignment_id', '=', 'search_pa.id')
                                ->join('issued_entitlements as search_ie', 'search_ie.expected_entitlement_id', '=', 'search_ee.id')
                                ->whereColumn('search_pa.person_id', 'pa.person_id')
                                ->where(fn (Builder $owner) => $owner
                                    ->whereColumn('search_pa.artist_engagement_id', 'pa.artist_engagement_id')
                                    ->orWhereColumn('search_pa.vendor_engagement_id', 'pa.vendor_engagement_id'))
                                ->when($passId !== null, fn (Builder $codes) => $codes->where('search_pa.pass_type_id', $passId))
                                ->whereRaw("lower(search_ie.code) like ? escape '!'", [$pattern]);
                        });
                });
            })
            ->groupBy('pa.person_id', 'pa.artist_engagement_id', 'pa.vendor_engagement_id')
            ->selectRaw("
                pa.person_id as person_id,
                COALESCE(pa.artist_engagement_id, pa.vendor_engagement_id) as engagement_id,
                CASE WHEN pa.artist_engagement_id IS NOT NULL THEN 'artist' ELSE 'vendor' END as type,
                MAX(p.name) as person_name,
                MAX(p.email) as person_email,
                MAX(COALESCE(a.name, v.name)) as context_name,
                NULL as group_name,
                NULL as role_name,
                COUNT(DISTINCT pa.id) > 0 as has_pass,
                {$passNames} as pass_name,
                COUNT(DISTINCT ee.id) as expected_count,
                COUNT(DISTINCT ie.id) as issued_count
            ");

        return $this->filterStatus($query, $status);
    }

    private function teamQuery(int $eventId, ?int $passId, string $search, string $status, string $type): Builder
    {
        $passNames = DB::connection()->getDriverName() === 'pgsql'
            ? "STRING_AGG(DISTINCT pt.name, ',' ORDER BY pt.name)"
            : 'GROUP_CONCAT(DISTINCT pt.name)';
        $query = DB::table('team_engagements as te')
            ->join('people as p', 'p.id', '=', 'te.person_id')
            ->leftJoin('groups as g', 'g.id', '=', 'te.group_id')
            ->leftJoin('roles as r', 'r.id', '=', 'te.role_id')
            ->leftJoin('pass_assignments as pa', function ($join) use ($eventId): void {
                $join->on('pa.team_engagement_id', '=', 'te.id')->on('pa.person_id', '=', 'te.person_id')
                    ->whereIn('pa.pass_type_id', DB::table('pass_types')->select('id')->where('event_id', $eventId));
            })
            ->leftJoin('pass_types as pt', 'pt.id', '=', 'pa.pass_type_id')
            ->leftJoin('expected_entitlements as ee', function ($join) use ($eventId): void {
                $join->on('ee.pass_assignment_id', '=', 'pa.id')
                    ->whereIn('ee.entitlement_item_id', DB::table('entitlement_items')->select('id')->where('event_id', $eventId));
            })
            ->leftJoin('issued_entitlements as ie', 'ie.expected_entitlement_id', '=', 'ee.id')
            ->where('te.event_id', $eventId)->where('te.status', 'hired')
            ->when(! in_array($type, ['all', 'team'], true), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($passId !== null, fn (Builder $query) => $query->where('pa.pass_type_id', $passId))
            ->when($search !== '', function (Builder $query) use ($search, $passId): void {
                $pattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';
                $query->where(function (Builder $query) use ($pattern, $passId): void {
                    $query->whereRaw("lower(p.name) like ? escape '!'", [$pattern])
                        ->orWhereExists(function (Builder $codes) use ($pattern, $passId): void {
                            $codes->selectRaw('1')->from('pass_assignments as search_pa')
                                ->join('expected_entitlements as search_ee', 'search_ee.pass_assignment_id', '=', 'search_pa.id')
                                ->join('issued_entitlements as search_ie', 'search_ie.expected_entitlement_id', '=', 'search_ee.id')
                                ->whereColumn('search_pa.team_engagement_id', 'te.id')
                                ->whereColumn('search_pa.person_id', 'te.person_id')
                                ->whereIn('search_pa.pass_type_id', DB::table('pass_types')->select('id')->whereColumn('event_id', 'te.event_id'))
                                ->when($passId !== null, fn (Builder $codes) => $codes->where('search_pa.pass_type_id', $passId))
                                ->whereRaw("lower(search_ie.code) like ? escape '!'", [$pattern]);
                        });
                });
            })
            ->groupBy('te.id', 'te.person_id')
            ->selectRaw("te.person_id as person_id, te.id as engagement_id, 'team' as type,
                MAX(p.name) as person_name, MAX(p.email) as person_email, NULL as context_name,
                MAX(g.name) as group_name, MAX(r.name) as role_name, COUNT(DISTINCT pa.id) > 0 as has_pass,
                {$passNames} as pass_name, COUNT(DISTINCT ee.id) as expected_count, COUNT(DISTINCT ie.id) as issued_count");

        return $this->filterStatus($query, $status);
    }

    private function filterStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'complete' => $query->havingRaw('COUNT(DISTINCT ee.id) = 0 OR COUNT(DISTINCT ie.id) >= COUNT(DISTINCT ee.id)'),
            'not_started' => $query->havingRaw('COUNT(DISTINCT ee.id) > 0 AND COUNT(DISTINCT ie.id) = 0'),
            'partial' => $query->havingRaw('COUNT(DISTINCT ie.id) > 0 AND COUNT(DISTINCT ie.id) < COUNT(DISTINCT ee.id)'),
            default => $query,
        };
    }
}
