<?php

namespace App\Repositories;

use App\Models\Shift;
use App\Models\TeamEngagement;
use App\Support\ShiftAssignmentHours;
use App\Support\ShiftAssignmentOverlaps;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShiftAssignmentRepository
{
    public function candidates(Shift $shift, array $data): LengthAwarePaginator
    {
        $slot = $shift->roleSlots()->findOrFail($data['shift_role_slot_id']);
        [$start, $end] = ShiftAssignmentHours::resolve($shift, $data);
        $pattern = '%'.SqlLike::escape(mb_strtolower(trim($data['search'] ?? ''))).'%';
        $candidates = TeamEngagement::query()->where('team_engagements.event_id', $shift->event_id)
            ->where('status', 'hired')->join('people', 'people.id', '=', 'team_engagements.person_id')
            ->select(['team_engagements.id', 'team_engagements.role_id', 'team_engagements.group_id', 'people.name as person_name'])
            ->with(['role:id,name', 'group:id,name'])
            ->withExists(['shiftAssignments as on_shift' => fn ($query) => $query->where('shift_id', $shift->id)])
            ->whereRaw("LOWER(people.name) LIKE ? ESCAPE '!'", [$pattern])
            ->orderByRaw('CASE WHEN team_engagements.role_id = ? THEN 0 ELSE 1 END', [$slot->role_id])
            ->orderByRaw('LOWER(people.name)')->orderBy('team_engagements.id')
            ->paginate((int) ($data['per_page'] ?? 5), ['*'], 'page', (int) ($data['page'] ?? 1))->withQueryString();
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
        $shift->load(['location:id,name', 'roleSlots.role:id,name', 'assignments.role:id,name', 'assignments.teamEngagement:id,person_id', 'assignments.teamEngagement.person:id,name']);
        $shift->loadCount('assignments');
        $assignments = $shift->assignments;
        $others = ShiftAssignmentOverlaps::forMembers($shift, $assignments->pluck('team_engagement_id')->unique()->all(), $shift->starts_at, $shift->ends_at);
        $positions = [];
        $slots = $shift->roleSlots->keyBy('id');
        foreach ($assignments as $assignment) {
            $slot = $slots->get($assignment->shift_role_slot_id);
            $matches = $slot !== null && (int) $slot->role_id === (int) $assignment->role_id;
            $index = $positions[$slot?->id] ?? 0;
            $assignment->setAttribute('is_extra', ! $matches || $index >= $slot->needed);
            if ($matches) {
                $positions[$slot->id] = $index + 1;
            }
            $assignment->setAttribute('overlaps', ShiftAssignmentOverlaps::warnings($others->get($assignment->team_engagement_id, collect()), $assignment->starts_at, $assignment->ends_at, $shift->event->timezone));
        }

        return $shift;
    }
}
