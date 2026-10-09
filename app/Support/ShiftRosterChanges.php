<?php

namespace App\Support;

use App\Models\Shift;
use Illuminate\Validation\ValidationException;

final class ShiftRosterChanges
{
    public static function errors(Shift $shift, array $data, bool $lock = false): array
    {
        $existing = $shift->assignments();
        if ($lock) {
            $existing->lockForUpdate();
        }
        $existing = $shift->exists ? $existing->get()->keyBy('id') : collect();
        $removals = $data['assignment_removals'] ?? [];
        $errors = [];
        if (isset($data['supervisor_key'])) {
            $key = $data['supervisor_key'];
            $rosterKeys = [...array_diff($existing->keys()->all(), $removals), ...array_column($data['assignment_additions'] ?? [], 'client_key')];
            if ((! is_int($key) && (! is_string($key) || ! preg_match('/^-?[1-9]\d*$/', $key)))
                || ! in_array($key, $rosterKeys)) {
                $errors['supervisor_key'] = __('team.scheduling.supervisor.errors.roster');
            }
        }
        foreach ($removals as $index => $id) {
            if (! $existing->has($id)) {
                $errors["assignment_removals.$index"] = __('team.scheduling.assignments.errors.stale_roster');
            }
        }
        $proposed = clone $shift;
        $proposed->starts_at = $data['starts_at'];
        $proposed->ends_at = $data['ends_at'];
        foreach (['assignment_updates', 'assignment_additions'] as $field) {
            foreach ($data[$field] ?? [] as $index => $row) {
                if ($field === 'assignment_updates' && (! $existing->has($row['id']) || in_array($row['id'], $removals))) {
                    $errors["$field.$index.id"] = __('team.scheduling.assignments.errors.stale_roster');
                }
                try {
                    ShiftAssignmentHours::resolve($proposed, $row);
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $key => $messages) {
                        $errors["$field.$index.$key"] = $messages[0];
                    }
                }
            }
        }

        $slots = collect($data['slots'] ?? []);
        $draftKeys = $slots->filter(fn ($row) => empty($row['id']))->pluck('client_key')->filter()->all();
        foreach ($data['assignment_additions'] ?? [] as $index => $row) {
            if (isset($row['slot_key']) && ! in_array($row['slot_key'], $draftKeys, true)) {
                $errors["assignment_additions.$index.slot_key"] = __('team.scheduling.slots.errors.foreign_slot');
            }
        }
        if ($shift->exists && array_key_exists('slots', $data)) {
            $savedSlots = $shift->roleSlots()->when($lock, fn ($query) => $query->lockForUpdate())->get();
            $counts = $existing->reject(fn ($assignment) => in_array($assignment->id, $removals))->countBy('shift_role_slot_id');
            foreach ($data['assignment_additions'] ?? [] as $row) {
                if (isset($row['shift_role_slot_id'])) {
                    $id = $row['shift_role_slot_id'];
                    $counts[$id] = ($counts[$id] ?? 0) + 1;
                }
            }
            foreach ($savedSlots as $slot) {
                $index = $slots->search(fn ($row) => isset($row['id']) && (int) $row['id'] === $slot->id);
                $count = $counts[$slot->id] ?? 0;
                if ($count === 0) {
                    continue;
                }
                if ($index === false) {
                    $errors['slots'] = __('team.scheduling.slots.errors.assigned_role');
                } elseif ((int) $slots[$index]['role_id'] !== (int) $slot->role_id) {
                    $errors["slots.$index.role_id"] = __('team.scheduling.slots.errors.assigned_role');
                } elseif ((int) $slots[$index]['needed'] < $slot->needed && (int) $slots[$index]['needed'] < $count) {
                    $errors["slots.$index.needed"] = __('team.scheduling.slots.errors.assigned_qty', ['count' => $count]);
                }
            }
        }

        return $errors;
    }
}
