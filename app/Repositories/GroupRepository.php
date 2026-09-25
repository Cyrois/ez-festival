<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\Group;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GroupRepository
{
    /** @return Collection<int, Group> */
    public function optionsFor(Event $event): Collection
    {
        return $event->groups()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return LengthAwarePaginator<int, Group> */
    public function paginateFor(Event $event, string $search): LengthAwarePaginator
    {
        $pattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';

        return $event->groups()
            ->withCount('teamEngagements')
            ->when(
                $search !== '',
                fn ($query) => $query->where(
                    fn ($searchQuery) => $searchQuery
                        ->whereRaw("lower(name) like ? escape '!'", [$pattern])
                        ->orWhereRaw("lower(description) like ? escape '!'", [$pattern]),
                ),
            )
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();
    }
}
