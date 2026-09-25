<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\TeamEngagement;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TeamEngagementRepository
{
    /**
     * @param  list<string>  $employmentTypes
     * @return LengthAwarePaginator<int, TeamEngagement>
     */
    public function paginate(?Event $event, string $search, array $employmentTypes): LengthAwarePaginator
    {
        return $this->listed($event, $search, $employmentTypes)
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @param  list<string>  $employmentTypes
     * @return Collection<int, TeamEngagement>
     */
    public function all(?Event $event, string $search, array $employmentTypes): Collection
    {
        return $this->listed($event, $search, $employmentTypes)->get();
    }

    /**
     * @param  list<string>  $employmentTypes
     * @return array<string, int>
     */
    public function statusCounts(?Event $event, string $search, array $employmentTypes): array
    {
        $counts = $this->filtered($event, $search, $employmentTypes)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(TeamEngagement::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => (int) ($counts[$status] ?? 0)])
            ->all();
    }

    /**
     * @param  list<string>  $employmentTypes
     * @return Builder<TeamEngagement>
     */
    private function listed(?Event $event, string $search, array $employmentTypes): Builder
    {
        return $this->filtered($event, $search, $employmentTypes)
            ->with(['person', 'group'])
            ->latest('id');
    }

    /**
     * @param  list<string>  $employmentTypes
     * @return Builder<TeamEngagement>
     */
    private function filtered(?Event $event, string $search, array $employmentTypes): Builder
    {
        $pattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';

        return TeamEngagement::query()
            ->where('event_id', $event?->id)
            ->when(
                $search !== '',
                fn (Builder $query) => $query->where(function (Builder $query) use ($pattern) {
                    $query->whereHas(
                        'person',
                        fn (Builder $query) => $query->where(
                            fn (Builder $query) => $query
                                ->whereRaw("lower(name) like ? escape '!'", [$pattern])
                                ->orWhereRaw("lower(coalesce(email, '')) like ? escape '!'", [$pattern]),
                        ),
                    );
                }),
            )
            ->when(
                $employmentTypes !== [],
                fn (Builder $query) => $query->whereIn('employment_type', $employmentTypes),
            );
    }
}
