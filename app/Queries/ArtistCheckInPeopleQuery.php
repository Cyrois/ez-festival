<?php

namespace App\Queries;

use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;

class ArtistCheckInPeopleQuery
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
        bool $canEdit,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = $this->baseQuery($eventId, $passId, $search, $status, $type)
            ->orderByRaw('MIN(lower(p.name))');

        /** @var LengthAwarePaginator<int, object> $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(function (object $row) use ($canEdit): object {
                $row->can_edit = $canEdit;

                return $row;
            }),
        );

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

    private function baseQuery(int $eventId, ?int $passId, string $search, string $status, string $type): Builder
    {
        $passNames = match (DB::connection()->getDriverName()) {
            'pgsql' => "STRING_AGG(DISTINCT pt.name, ',' ORDER BY pt.name)",
            'mysql', 'mariadb' => "GROUP_CONCAT(DISTINCT pt.name ORDER BY pt.name SEPARATOR ',')",
            default => 'GROUP_CONCAT(DISTINCT pt.name)',
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
            ->where(fn (Builder $query) => $query->whereNotNull('ae.id')->orWhereNotNull('ve.id'))
            ->when($type === 'artist', fn (Builder $query) => $query->whereNotNull('ae.id'))
            ->when($type === 'vendor', fn (Builder $query) => $query->whereNotNull('ve.id'))
            ->when($passId !== null, fn (Builder $query) => $query->where('pa.pass_type_id', $passId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw("lower(p.name) like ? escape '!'", [$pattern])
                        ->orWhereRaw("lower(p.email) like ? escape '!'", [$pattern])
                        ->orWhereRaw("lower(a.name) like ? escape '!'", [$pattern])
                        ->orWhereRaw("lower(v.name) like ? escape '!'", [$pattern])
                        ->orWhereRaw("lower(ie.code) like ? escape '!'", [$pattern]);
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
                {$passNames} as pass_name,
                COUNT(DISTINCT ee.id) as expected_count,
                COUNT(DISTINCT ie.id) as issued_count
            ");

        return match ($status) {
            'complete' => $query->havingRaw('COUNT(DISTINCT ee.id) = 0 OR COUNT(DISTINCT ie.id) >= COUNT(DISTINCT ee.id)'),
            'not_started' => $query->havingRaw('COUNT(DISTINCT ee.id) > 0 AND COUNT(DISTINCT ie.id) = 0'),
            'partial' => $query->havingRaw('COUNT(DISTINCT ie.id) > 0 AND COUNT(DISTINCT ie.id) < COUNT(DISTINCT ee.id)'),
            default => $query,
        };
    }
}
