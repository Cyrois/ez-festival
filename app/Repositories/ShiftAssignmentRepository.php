<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\TeamEngagement;
use App\Queries\MealEntitlementQuery;
use App\Support\ShiftAssignmentHours;
use App\Support\ShiftAssignmentOverlaps;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ShiftAssignmentRepository
{
    /** @return array{total: int, filtered: int, rows: Collection<int, ShiftAssignment>} */
    public function memberDataTable(
        Event $event,
        TeamEngagement $engagement,
        string $search,
        int $orderColumn,
        string $orderDirection,
        int $start,
        int $length,
    ): array {
        $query = $engagement->shiftAssignments()
            ->join('shifts', 'shifts.id', '=', 'shift_assignments.shift_id')
            ->join('locations', 'locations.id', '=', 'shifts.location_id')
            ->leftJoin('roles', 'roles.id', '=', 'shift_assignments.role_id')
            ->where('shifts.event_id', $event->id)
            ->select([
                'shift_assignments.id', 'shift_assignments.shift_id', 'shift_assignments.starts_at', 'shift_assignments.ends_at',
                'locations.name as location_name', 'roles.name as role_name',
            ]);
        $total = (clone $query)->count();

        if ($search !== '') {
            $pattern = '%'.SqlLike::escape(mb_strtolower($search)).'%';
            $query->where(function ($query) use ($pattern): void {
                $query->whereRaw("LOWER(locations.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(roles.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("CAST(shift_assignments.starts_at AS TEXT) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("CAST(shift_assignments.ends_at AS TEXT) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        $filtered = (clone $query)->count();
        $direction = $orderDirection === 'desc' ? 'desc' : 'asc';
        match ($orderColumn) {
            1 => $query->orderByRaw("LOWER(locations.name) {$direction}"),
            3 => $query->orderByRaw("LOWER(COALESCE(roles.name, '')) {$direction}"),
            default => $query->orderBy('shift_assignments.starts_at', $direction),
        };
        if ($orderColumn === 1 || $orderColumn === 3) {
            $query->orderBy('shift_assignments.starts_at');
        }

        return [
            'total' => $total,
            'filtered' => $filtered,
            'rows' => $query->orderBy('shift_assignments.id')->skip($start)->take($length)->get(),
        ];
    }

    public function copySource(Shift $shift): Shift
    {
        return $shift->load(['event', 'breaks', 'location:id,name', 'roleSlots.role:id,name', 'assignments.breaks', 'assignments.role:id,name', 'assignments.teamEngagement.person:id,name']);
    }

    /** Other assignments within the proposed timeline, including its 30-minute padding. */
    public function nearbyAssignments(Shift $shift, int $memberId): Collection
    {
        return ShiftAssignmentOverlaps::forMembers($shift, [$memberId],
            $shift->starts_at->copy()->subMinutes(30), $shift->ends_at->copy()->addMinutes(30))
            ->get($memberId, collect());
    }

    public function candidates(Shift $shift, array $data): LengthAwarePaginator
    {
        $extra = filter_var($data['extra'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $roleId = isset($data['shift_role_slot_id'])
            ? $shift->roleSlots()->findOrFail($data['shift_role_slot_id'])->role_id
            : ($data['role_id'] ?? null);
        [$start, $end] = ShiftAssignmentHours::resolve($shift, $data);
        $pattern = '%'.SqlLike::escape(mb_strtolower(trim($data['search'] ?? ''))).'%';
        // Rank the entire scoped set before pagination, using the same interval
        // boundaries and event scope as the displayed overlap warnings.
        $onShift = ShiftAssignment::query()->selectRaw('1')
            ->whereColumn('team_engagement_id', 'team_engagements.id')->where('shift_id', $shift->id);
        $overlapping = ShiftAssignment::query()->selectRaw('1')
            ->whereColumn('team_engagement_id', 'team_engagements.id')->where('shift_id', '!=', $shift->id)
            ->whereHas('shift', fn ($query) => $query->where('event_id', $shift->event_id))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start);
        $candidates = TeamEngagement::query()->where('team_engagements.event_id', $shift->event_id)
            ->where('team_engagements.status', 'hired')->join('people', 'people.id', '=', 'team_engagements.person_id')
            ->select(['team_engagements.id', 'team_engagements.role_id', 'team_engagements.group_id', 'people.name as person_name'])
            ->selectRaw('CASE WHEN EXISTS ('.$onShift->toSql().') THEN 2 WHEN EXISTS ('.$overlapping->toSql().') THEN 1 ELSE 0 END AS availability_order',
                [...$onShift->getBindings(), ...$overlapping->getBindings()])
            ->with(['role:id,name', 'group:id,name'])
            ->withExists(['shiftAssignments as on_shift' => fn ($query) => $query->where('shift_id', $shift->id)])
            ->whereRaw("LOWER(people.name) LIKE ? ESCAPE '!'", [$pattern])
            ->when(! $extra && ($data['role_filter'] ?? 'everyone') === 'has_role', fn ($query) => $query->where('team_engagements.role_id', $roleId))
            ->when(isset($data['selected_id']), fn ($query) => $query->where('team_engagements.id', $data['selected_id']))
            ->orderBy('availability_order')
            ->when(! $extra, fn ($query) => $query->orderByRaw('CASE WHEN team_engagements.role_id = ? THEN 0 ELSE 1 END', [$roleId]))
            ->orderByRaw('LOWER(people.name)')->orderBy('team_engagements.id')
            ->paginate((int) ($data['per_page'] ?? 25), ['*'], 'page', (int) ($data['page'] ?? 1))->withQueryString();
        $others = ShiftAssignmentOverlaps::forMembers($shift, $candidates->getCollection()->modelKeys(), $shift->starts_at->copy()->subMinutes(30), $shift->ends_at->copy()->addMinutes(30));
        $timezone = $shift->event->timezone;
        foreach ($candidates as $candidate) {
            $candidate->setAttribute('suggested', ! $extra && (int) $candidate->role_id === (int) $roleId);
            $candidate->setAttribute('other_shifts', ShiftAssignmentOverlaps::shifts($others->get($candidate->id, collect())));
            $candidate->setAttribute('overlaps', ShiftAssignmentOverlaps::warnings($others->get($candidate->id, collect()), $start, $end, $timezone));
        }

        return $candidates;
    }

    public function loadRoster(Shift $shift): Shift
    {
        $shift->load(['breaks', 'location:id,name', 'roleSlots.role:id,name', 'assignments.breaks', 'assignments.role:id,name', 'assignments.teamEngagement:id,person_id', 'assignments.teamEngagement.person:id,name']);
        app(MealEntitlementQuery::class)->loadForShifts(new \Illuminate\Database\Eloquent\Collection([$shift]), $shift->event);
        $shift->loadCount('assignments');
        $assignments = $shift->assignments;
        $others = ShiftAssignmentOverlaps::forMembers($shift, $assignments->pluck('team_engagement_id')->unique()->all(), $shift->starts_at->copy()->subMinutes(30), $shift->ends_at->copy()->addMinutes(30));

        return $this->decorateRoster($shift, $others, $shift->event->timezone);
    }

    /** Load a page of rosters and overlaps in batches, using the shift-page rules. */
    public function loadRosters(Collection $shifts, Event $event): void
    {
        if ($shifts->isEmpty()) {
            return;
        }
        $shifts->load([
            'roleSlots.role:id,name', 'assignments.breaks', 'assignments.role:id,name',
            'assignments.teamEngagement:id,person_id', 'assignments.teamEngagement.person:id,name',
        ]);
        app(MealEntitlementQuery::class)->loadForShifts($shifts, $event);
        $assignments = $shifts->flatMap(fn ($shift) => $shift->assignments);
        $others = ShiftAssignment::query()
            ->whereIn('team_engagement_id', $assignments->pluck('team_engagement_id')->unique())
            ->whereHas('shift', fn ($query) => $query->where('event_id', $event->id))
            ->where('starts_at', '<', $shifts->max('ends_at'))->where('ends_at', '>', $shifts->min('starts_at'))
            ->with('shift:id,name,color')->orderBy('starts_at')->orderBy('id')->get();
        foreach ($shifts as $shift) {
            $this->decorateRoster($shift, $others->where('shift_id', '!=', $shift->id)->groupBy('team_engagement_id'), $event->timezone);
        }
    }

    private function decorateRoster(Shift $shift, Collection $others, string $timezone): Shift
    {
        $assignments = $shift->assignments;
        $positions = [];
        $slots = $shift->roleSlots->keyBy('id');
        foreach ($assignments as $assignment) {
            $assignment->setAttribute('scheduled_minutes', ShiftAssignmentHours::scheduledMinutes($assignment, $assignment->breaks->sum('duration_minutes')));
            $slot = $slots->get($assignment->shift_role_slot_id);
            $matches = $slot !== null && (int) $slot->role_id === (int) $assignment->role_id;
            $index = $positions[$slot?->id] ?? 0;
            $assignment->setAttribute('is_extra', ! $matches || $index >= $slot->needed);
            if ($matches) {
                $positions[$slot->id] = $index + 1;
            }
            $assignment->setAttribute('overlaps', ShiftAssignmentOverlaps::warnings($others->get($assignment->team_engagement_id, collect()), $assignment->starts_at, $assignment->ends_at, $timezone));
            $assignment->setAttribute('other_shifts', ShiftAssignmentOverlaps::shifts($others->get($assignment->team_engagement_id, collect())));
        }

        return $shift;
    }
}
