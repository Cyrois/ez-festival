<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Shift;
use App\Models\TeamEngagement;
use Illuminate\Validation\ValidationException;

final class ShiftCopyAssignments
{
    public static function eligibilityError(?TeamEngagement $member): ?string
    {
        return $member === null || $member->status !== 'hired'
            ? __('team.scheduling.assignments.errors.eligible') : null;
    }

    /** Resolve the entire draft in batches, during validation and again under the event write lock. */
    public static function resolve(Event $event, array $data, bool $lock = false): array
    {
        $source = isset($data['copy']) ? $event->shifts()->when($lock, fn ($query) => $query->lockForUpdate())->find($data['copy']) : null;
        if (isset($data['copy']) && $source === null) {
            throw ValidationException::withMessages(['copy' => __('team.scheduling.copy.errors.source')]);
        }
        $assignments = $data['assignments'] ?? [];
        if ($assignments === []) {
            return [];
        }
        if ($source === null) {
            throw ValidationException::withMessages(['copy' => __('team.scheduling.copy.errors.source')]);
        }
        $originals = $source->assignments()->when($lock, fn ($query) => $query->lockForUpdate())->get()->keyBy('team_engagement_id');
        $members = TeamEngagement::query()->where('event_id', $event->id)
            ->whereIn('id', array_column($assignments, 'team_engagement_id'))->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())->get()->keyBy('id');
        $counts = array_count_values(array_map(fn ($row) => (int) $row['team_engagement_id'], $assignments));
        $shift = new Shift(['event_id' => $event->id, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at']]);
        $slots = $data['slots'] ?? [];
        $errors = [];
        $resolved = [];
        foreach ($assignments as $index => $row) {
            $memberId = (int) $row['team_engagement_id'];
            $original = $originals->get($memberId);
            $reason = self::eligibilityError($members->get($memberId));
            if ($reason !== null || $original === null) {
                $errors["assignments.$index.team_engagement_id"] = $reason ?? __('team.scheduling.copy.errors.person');
            }
            if ($counts[$memberId] > 1) {
                $errors["assignments.$index.team_engagement_id"] = __('team.scheduling.assignments.errors.duplicate');
            }
            $slotIndex = $row['slot_index'];
            if ($slotIndex !== null && ! array_key_exists($slotIndex, $slots)) {
                $errors["assignments.$index.slot_index"] = __('team.scheduling.slots.errors.foreign_slot');
            }
            try {
                [$start, $end] = ShiftAssignmentHours::resolve($shift, $row);
                $resolved[$index] = [
                    'team_engagement_id' => $memberId,
                    'slot_index' => $slotIndex,
                    // Detached extras keep their saved role; attached rows take their draft slot's role.
                    'role_id' => $slotIndex === null ? $original?->role_id : ($slots[$slotIndex]['role_id'] ?? null),
                    'starts_at' => $start, 'ends_at' => $end,
                ];
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    $errors["assignments.$index.$field"] = $messages;
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $resolved;
    }

    public static function overlaps(Event $event, array $data, array $rows): array
    {
        $shift = new Shift(['event_id' => $event->id, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at']]);
        // An unsaved shift has no id, so include the source shift in overlap warnings.
        $others = ShiftAssignmentOverlaps::forMembers($shift, array_column($rows, 'team_engagement_id'), $shift->starts_at, $shift->ends_at);

        return array_map(fn ($row) => ShiftAssignmentOverlaps::warnings(
            $others->get($row['team_engagement_id'], collect()), $row['starts_at'], $row['ends_at'], $event->timezone,
        ), $rows);
    }
}
