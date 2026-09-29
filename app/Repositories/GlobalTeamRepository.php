<?php

namespace App\Repositories;

use App\Models\Person;
use App\Support\SqlLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GlobalTeamRepository
{
    /**
     * @return array{people: Collection<int, Person>, total: int, filtered: int}
     */
    public function dataTable(
        string $search,
        int $start,
        int $length,
        int $orderColumn,
        string $direction,
    ): array {
        $query = $this->query();
        $filteredQuery = clone $query;
        $pattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';

        if ($search !== '') {
            $filteredQuery->where(
                fn (Builder $query) => $query
                    ->whereRaw("lower(name) like ? escape '!'", [$pattern])
                    ->orWhereRaw("lower(email) like ? escape '!'", [$pattern]),
            );
        }

        $orderBy = $orderColumn === 1 ? 'email' : 'name';

        return [
            'people' => $filteredQuery
                ->with(['teamEngagements' => fn ($query) => $query
                    ->whereNotNull('role_id')
                    ->with(['event', 'role'])
                    ->orderBy('event_id')])
                ->orderBy($orderBy, $direction)
                ->orderBy('id')
                ->offset($start)
                ->limit($length)
                ->get(),
            'total' => (clone $query)->count(),
            'filtered' => (clone $filteredQuery)->count(),
        ];
    }

    public function exists(): bool
    {
        return $this->query()->exists();
    }

    /** @return Builder<Person> */
    private function query(): Builder
    {
        return Person::query()
            ->whereHas('teamEngagements', fn (Builder $query) => $query->whereNotNull('role_id'));
    }
}
