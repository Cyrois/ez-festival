<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\TeamEngagement;
use App\Support\ShiftAssignmentHours;
use App\Support\ShiftAssignmentOverlaps;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ShiftAssignmentRepository
{
    public function candidates(Shift $shift, array $data): LengthAwarePaginator
    {
        $slot = $shift->roleSlots()->findOrFail($data['shift_role_slot_id']);
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
            ->when(($data['role_filter'] ?? 'everyone') === 'has_role', fn ($query) => $query->where('team_engagements.role_id', $slot->role_id))
            ->when(isset($data['selected_id']), fn ($query) => $query->where('team_engagements.id', $data['selected_id']))
            ->orderBy('availability_order')
            ->orderByRaw('CASE WHEN team_engagements.role_id = ? THEN 0 ELSE 1 END', [$slot->role_id])
            ->orderByRaw('LOWER(people.name)')->orderBy('team_engagements.id')
            ->paginate((int) ($data['per_page'] ?? 25), ['*'], 'page', (int) ($data['page'] ?? 1))->withQueryString();
        $others = ShiftAssignmentOverlaps::forMembers($shift, $candidates->getCollection()->modelKeys(), $start, $end);
        $timezone = $shift->event->timezone;
        foreach ($candidates as $candidate) {
            $candidate->setAttribute('suggested', (int) $candidate->role_id === (int) $slot->role_id);
            $candidate->setAttribute('overlaps', ShiftAssignmentOverlaps::warnings($others->get($candidate->id, collect()), $start, $end, $timezone));
        }

        return $candidates;
    }

    public function loadRoster(Shift $shift): Shift
    {
        $shift->load(['breaks', 'location:id,name', 'roleSlots.role:id,name', 'assignments.role:id,name', 'assignments.teamEngagement:id,person_id', 'assignments.teamEngagement.person:id,name']);
        $shift->loadCount('assignments');
        $assignments = $shift->assignments;
        $others = ShiftAssignmentOverlaps::forMembers($shift, $assignments->pluck('team_engagement_id')->unique()->all(), $shift->starts_at, $shift->ends_at);

        return $this->decorateRoster($shift, $others, $shift->event->timezone);
    }

    /** Load a page of rosters and overlaps in batches, using the shift-page rules. */
    public function loadRosters(Collection $shifts, Event $event): void
    {
        if ($shifts->isEmpty()) {
            return;
        }
        $shifts->load([
            'roleSlots.role:id,name', 'assignments.role:id,name',
            'assignments.teamEngagement:id,person_id', 'assignments.teamEngagement.person:id,name',
        ]);
        $shifts->loadSum('breaks as break_minutes', 'duration_minutes');
        $assignments = $shifts->flatMap(fn ($shift) => $shift->assignments);
        $others = ShiftAssignment::query()
            ->whereIn('team_engagement_id', $assignments->pluck('team_engagement_id')->unique())
            ->whereHas('shift', fn ($query) => $query->where('event_id', $event->id))
            ->where('starts_at', '<', $shifts->max('ends_at'))->where('ends_at', '>', $shifts->min('starts_at'))
            ->with('shift:id,name')->orderBy('starts_at')->orderBy('id')->get();
        foreach ($shifts as $shift) {
            $this->decorateRoster($shift, $others->where('shift_id', '!=', $shift->id)->groupBy('team_engagement_id'), $event->timezone);
        }
    }

    private function decorateRoster(Shift $shift, Collection $others, string $timezone): Shift
    {
        $assignments = $shift->assignments;
        $positions = [];
        $slots = $shift->roleSlots->keyBy('id');
        $breakMinutes = $shift->relationLoaded('breaks') ? $shift->breaks->sum('duration_minutes') : (int) $shift->break_minutes;
        foreach ($assignments as $assignment) {
            $assignment->setAttribute('scheduled_minutes', ShiftAssignmentHours::scheduledMinutes($assignment, $breakMinutes));
            $slot = $slots->get($assignment->shift_role_slot_id);
            $matches = $slot !== null && (int) $slot->role_id === (int) $assignment->role_id;
            $index = $positions[$slot?->id] ?? 0;
            $assignment->setAttribute('is_extra', ! $matches || $index >= $slot->needed);
            if ($matches) {
                $positions[$slot->id] = $index + 1;
            }
            $assignment->setAttribute('overlaps', ShiftAssignmentOverlaps::warnings($others->get($assignment->team_engagement_id, collect()), $assignment->starts_at, $assignment->ends_at, $timezone));
        }

        return $shift;
    }
}
