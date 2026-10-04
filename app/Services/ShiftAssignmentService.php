<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\TeamEngagement;
use App\Repositories\ShiftAssignmentRepository;
use App\Support\ShiftAssignmentHours;
use App\Support\ShiftAssignmentOverlaps;
use App\Support\ShiftCopyAssignments;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftAssignmentService
{
    public function create(Shift $shift, array $data): ShiftAssignment
    {
        return DB::transaction(function () use ($shift, $data): ShiftAssignment {
            $event = Event::query()->lockForUpdate()->findOrFail($shift->event_id);
            $event->ensureWritable();
            $shift = $event->shifts()->lockForUpdate()->findOrFail($shift->id);
            $slot = $shift->roleSlots()->lockForUpdate()->find($data['shift_role_slot_id']);
            if ($slot === null) {
                throw ValidationException::withMessages(['shift_role_slot_id' => __('team.scheduling.slots.errors.foreign_slot')]);
            }
            $member = TeamEngagement::query()->where('event_id', $event->id)->lockForUpdate()->find($data['team_engagement_id']);
            if (($reason = ShiftCopyAssignments::eligibilityError($member)) !== null) {
                throw ValidationException::withMessages(['team_engagement_id' => $reason]);
            }
            if ($shift->assignments()->where('team_engagement_id', $member->id)->exists()) {
                $this->duplicate();
            }
            [$start, $end] = ShiftAssignmentHours::resolve($shift, $data);
            try {
                // A savepoint rolls back a PostgreSQL unique violation before it is handled.
                return DB::transaction(fn () => $shift->assignments()->create([
                    'team_engagement_id' => $member->id, 'shift_role_slot_id' => $slot->id,
                    'role_id' => $slot->role_id, 'starts_at' => $start, 'ends_at' => $end,
                ]));
            } catch (UniqueConstraintViolationException) {
                $this->duplicate();
            }
        });
    }

    private function duplicate(): never
    {
        throw ValidationException::withMessages(['team_engagement_id' => __('team.scheduling.assignments.errors.duplicate')]);
    }

    /** Preview proposed hours without changing the saved shift or assignment. */
    public function previewOverlaps(Shift $shift, ?ShiftAssignment $assignment, array $data): array
    {
        $proposedShift = clone $shift;
        if (isset($data['shift_starts_at'], $data['shift_ends_at'])) {
            $proposedShift->starts_at = $data['shift_starts_at'];
            $proposedShift->ends_at = $data['shift_ends_at'];
        }
        [$start, $end] = ShiftAssignmentHours::resolve($proposedShift, $data);
        $memberId = $assignment?->team_engagement_id ?? $data['team_engagement_id'];
        $others = app(ShiftAssignmentRepository::class)->nearbyAssignments($proposedShift, $memberId);

        return [
            'warnings' => ShiftAssignmentOverlaps::warnings($others, $start, $end, $shift->event->timezone),
            'other_shifts' => ShiftAssignmentOverlaps::shifts($others),
        ];
    }

    public function delete(Shift $shift, ShiftAssignment $assignment): void
    {
        DB::transaction(function () use ($shift, $assignment): void {
            $event = Event::query()->lockForUpdate()->findOrFail($shift->event_id);
            $event->ensureWritable();
            $shift = $event->shifts()->lockForUpdate()->findOrFail($shift->id);
            $shift->assignments()->lockForUpdate()->findOrFail($assignment->id)->delete();
        });
    }
}
