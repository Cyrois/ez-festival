<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MealAssignment;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\TeamEngagement;
use App\Repositories\ShiftAssignmentRepository;
use App\Repositories\ShiftBreakRepository;
use App\Support\ShiftAssignmentHours;
use App\Support\ShiftAssignmentOverlaps;
use App\Support\ShiftMeals;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftAssignmentService
{
    public static function eligibilityError(?TeamEngagement $member): ?string
    {
        return $member === null || $member->status !== 'hired' ? __('team.scheduling.assignments.errors.eligible') : null;
    }

    public function create(Shift $shift, array $data, array $draftBreaks = []): ShiftAssignment
    {
        return DB::transaction(function () use ($shift, $data, $draftBreaks): ShiftAssignment {
            $event = Event::query()->lockForUpdate()->findOrFail($shift->event_id);
            $event->ensureWritable();
            $shift = $event->shifts()->lockForUpdate()->findOrFail($shift->id);
            $extra = filter_var($data['extra'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($extra && (isset($data['shift_role_slot_id']) || isset($data['role_id']) || isset($data['slot_key']))) {
                throw ValidationException::withMessages(['extra' => __('team.scheduling.assignments.errors.unexpected')]);
            }
            $detached = ! $extra && isset($data['role_id']) && ! isset($data['shift_role_slot_id']);
            $role = $detached ? Role::query()->lockForUpdate()->find($data['role_id']) : null;
            if ($detached && $role === null) {
                throw ValidationException::withMessages(['role_id' => __('validation.exists', ['attribute' => 'role'])]);
            }
            $slot = ($extra || $detached) ? null : $shift->roleSlots()->lockForUpdate()->find($data['shift_role_slot_id'] ?? null);
            if (! $extra && ! $detached && $slot === null) {
                throw ValidationException::withMessages(['shift_role_slot_id' => __('team.scheduling.slots.errors.foreign_slot')]);
            }
            $member = TeamEngagement::query()->where('event_id', $event->id)->lockForUpdate()->find($data['team_engagement_id']);
            if (($reason = self::eligibilityError($member)) !== null) {
                throw ValidationException::withMessages(['team_engagement_id' => $reason]);
            }
            if ($shift->assignments()->where('team_engagement_id', $member->id)->exists()) {
                $this->duplicate();
            }
            [$start, $end] = ShiftAssignmentHours::resolve($shift, $data);
            try {
                // A savepoint rolls back a PostgreSQL unique violation before it is handled.
                $assignment = DB::transaction(fn () => $shift->assignments()->create([
                    'team_engagement_id' => $member->id, 'shift_role_slot_id' => $slot?->id,
                    'role_id' => $slot?->role_id ?? $role?->id, 'starts_at' => $start, 'ends_at' => $end,
                ]));
                $assignment->setRelation('shift', $shift);
                $breaks = $data['breaks'] ?? app(ShiftBreakService::class)->snapshot(app(ShiftBreakRepository::class)->defaults($shift), $start, $end);
                app(ShiftBreakService::class)->sync($assignment, $breaks, $draftBreaks);

                return $assignment;
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
            $errors = ShiftMeals::removalErrors($shift, $assignment->id);
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            MealAssignment::query()->where('shift_assignment_id', $assignment->id)->update(['is_active' => false]);
            $shift->assignments()->lockForUpdate()->findOrFail($assignment->id)->delete();
        });
    }
}
