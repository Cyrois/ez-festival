<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\Location;
use App\Models\Shift;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ShiftRepository
{
    public function schedule(Event $event, string $date, int $page = 1, ?int $locationId = null): LengthAwarePaginator
    {
        [$start, $end] = $this->dayBounds($date);

        // Page location rows, keeping every shift that overlaps the selected day.
        // Strict bounds include overnight shifts without including ones ending at midnight.
        return $event->locations()->select(['id', 'name', 'event_id'])
            ->when($locationId !== null, fn ($query) => $query->where('id', $locationId))
            ->with(['shifts' => fn ($query) => $query
                ->where('event_id', $event->id)
                ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
                ->withCount('assignments')->with(['roleSlots', 'meals.meal.mealType', 'supervisorAssignment:id,shift_id,team_engagement_id', 'supervisorAssignment.teamEngagement:id,person_id', 'supervisorAssignment.teamEngagement.person:id,name'])
                ->orderBy('starts_at')->orderBy('id')])
            ->orderBy('name')->orderBy('id')
            ->paginate(25, ['*'], 'page', $page);
    }

    public function scheduleRosters(Event $event, string $date, int $locationId): Collection
    {
        [$start, $end] = $this->dayBounds($date);

        // The selected location and day bound this view; all matching shifts must load together.
        // The controller then loads their rosters and overlaps in batches.
        return $event->shifts()->where('location_id', $locationId)
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->withCount('assignments')->orderBy('starts_at')->orderBy('id')
            ->get();
    }

    public function countForDay(Event $event, string $date, ?int $locationId = null): int
    {
        [$start, $end] = $this->dayBounds($date);

        return $event->shifts()->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))->count();
    }

    public function firstShiftMinuteForDay(Event $event, string $date, ?int $locationId = null): ?int
    {
        [$start, $end] = $this->dayBounds($date);
        $first = $event->shifts()->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))->min('starts_at');
        if ($first === null) {
            return null;
        }

        if ($first < $start) {
            // An overnight shift is already visible at midnight on the selected day.
            return 0;
        }

        $time = Carbon::parse($first);

        return $time->hour * 60 + $time->minute;
    }

    private function dayBounds(string $date): array
    {
        // Stored shift timestamps represent wall-clock values in the event's timezone.
        // Use matching naive day boundaries instead of converting them from UTC.
        $start = Carbon::createFromFormat('!Y-m-d', $date);

        return [$start->format('Y-m-d H:i:s'), $start->copy()->addDay()->format('Y-m-d H:i:s')];
    }

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
            ->withCount('assignments')
            ->with(['location:id,name', 'roleSlots.role:id,name', 'meals.meal.mealType', 'supervisorAssignment:id,shift_id,team_engagement_id', 'supervisorAssignment.teamEngagement:id,person_id', 'supervisorAssignment.teamEngagement.person:id,name']);
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
