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
        $existing = $existing->get()->keyBy('id');
        $removals = $data['assignment_removals'] ?? [];
        $errors = [];
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

        return $errors;
    }
}
