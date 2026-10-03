<?php

namespace App\Repositories;

use App\Models\Person;
use App\Models\Role;
use App\Support\RoleName;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoleRepository
{
    public const STATUS_ON = 'on';

    public const STATUS_OFF = 'off';

    public const STATUS_ALL = 'all';

    public const STATUSES = [self::STATUS_ON, self::STATUS_OFF, self::STATUS_ALL];

    /** @return LengthAwarePaginator<int, Role> */
    public function paginate(string $search, string $status): LengthAwarePaginator
    {
        $pattern = '%'.SqlLike::escape(RoleName::key($search)).'%';

        return $this->withPeopleCount()
            ->when(
                $search !== '',
                fn (Builder $query) => $query->whereRaw("name_key like ? escape '!'", [$pattern]),
            )
            ->when($status === self::STATUS_ON, fn (Builder $query) => $query->where('active', true))
            ->when($status === self::STATUS_OFF, fn (Builder $query) => $query->where('active', false))
            ->orderByDesc('active')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @return array{roles: Collection<int, Role>, total: int, filtered: int}
     */
    public function dataTable(
        string $search,
        string $status,
        int $start,
        int $length,
        string $direction,
    ): array {
        $statusQuery = $this->forStatus($status);
        $filteredQuery = clone $statusQuery;

        if ($search !== '') {
            $pattern = '%'.SqlLike::escape(RoleName::key($search)).'%';
            $filteredQuery->whereRaw("name_key like ? escape '!'", [$pattern]);
        }

        $total = (clone $statusQuery)->count();
        $filtered = (clone $filteredQuery)->count();

        return [
            'roles' => $filteredQuery
                ->orderBy('name_key', $direction)
                ->orderBy('id')
                ->offset($start)
                ->limit($length)
                ->get(),
            'total' => $total,
            'filtered' => $filtered,
        ];
    }

    public function exists(): bool
    {
        return Role::query()->exists();
    }

    public function peopleDataTable(Role $role, string $search, int $start, int $length, string $direction): array
    {
        $query = Person::query()->whereHas('teamEngagements', fn (Builder $query) => $query->where('role_id', $role->id));
        $total = (clone $query)->count();
        if ($search !== '') {
            $query->whereRaw("lower(name) like ? escape '!'", ['%'.SqlLike::escape(mb_strtolower($search)).'%']);
        }

        return [
            'total' => $total,
            'filtered' => (clone $query)->count(),
            'people' => $query->orderBy('name', $direction)->orderBy('id')->offset($start)->limit($length)->get(['id', 'name']),
        ];
    }

    public function findByName(string $name, ?Role $ignore = null): ?Role
    {
        return Role::withTrashed()
            ->where('name_key', RoleName::key($name))
            ->when($ignore !== null, fn (Builder $query) => $query->whereKeyNot($ignore->getKey()))
            ->first();
    }

    /** @return Builder<Role> */
    private function forStatus(string $status): Builder
    {
        return $this->withPeopleCount()
            ->when($status === self::STATUS_ON, fn (Builder $query) => $query->where('active', true))
            ->when($status === self::STATUS_OFF, fn (Builder $query) => $query->where('active', false));
    }

    /** @return Builder<Role> */
    private function withPeopleCount(): Builder
    {
        return Role::query()->withCount([
            'teamEngagements as people_count' => fn (Builder $query) => $query->select(
                DB::raw('count(distinct person_id)'),
            ),
        ]);
    }
}
