<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\Location;
use App\Models\Shift;
use App\Support\SqlLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ShiftRepository
{
    /**
     * Server-side DataTables query for the current event's shifts.
     *
     * @return array{total: int, filtered: int, rows: Collection<int, Shift>}
     */
    public function dataTable(
        Event $event,
        string $search,
        int $orderColumn,
        string $orderDirection,
        int $start,
        int $length,
    ): array {
        $query = Shift::query()
            ->whereBelongsTo($event)
            ->with('location:id,name');
        $total = (clone $query)->count();

        if ($search !== '') {
            $pattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';

            $query->where(function (Builder $query) use ($pattern): void {
                $query
                    ->whereRaw("LOWER(shifts.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('location', fn (Builder $locationQuery) => $locationQuery
                        ->whereRaw("LOWER(locations.name) LIKE ? ESCAPE '!'", [$pattern]));
            });
        }

        $filtered = (clone $query)->count();
        $this->applyOrder($query, $orderColumn, $orderDirection === 'desc' ? 'desc' : 'asc');

        return [
            'total' => $total,
            'filtered' => $filtered,
            'rows' => $query->skip($start)->take($length)->get(),
        ];
    }

    /** @param Builder<Shift> $query */
    private function applyOrder(Builder $query, int $column, string $direction): void
    {
        match ($column) {
            0 => $query->orderByRaw("LOWER(COALESCE(shifts.name, '')) {$direction}"),
            1 => $query->orderBy(
                Location::query()
                    ->select('name')
                    ->whereColumn('locations.id', 'shifts.location_id'),
                $direction,
            ),
            2 => $query->orderBy('starts_at', $direction),
            default => $query->orderBy('ends_at', $direction),
        };

        $query->orderBy('shifts.id');
    }
}
