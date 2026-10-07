<?php

namespace App\Support;

use App\Models\Meal;
use App\Models\Shift;
use Illuminate\Support\Carbon;

final class ShiftMeals
{
    /** Validate against the final roster, including this Save's draft additions. */
    public static function errors(Shift $shift, array $data): array
    {
        $rows = $data['meals'] ?? ($shift->exists
            ? $shift->meals()->with('assignments:id')->get()->map(fn ($row) => [
                'meal_id' => $row->meal_id,
                'assignment_keys' => $row->assignments->pluck('id')->diff($data['assignment_removals'] ?? [])->values()->all(),
            ])->all() : []);
        $keys = $shift->exists ? $shift->assignments()->pluck('id')->all() : [];
        $keys = array_diff($keys, $data['assignment_removals'] ?? []);
        $keys = [...$keys, ...array_column($data['assignment_additions'] ?? [], 'client_key')];
        $meals = Meal::query()->where('event_id', $shift->event_id)
            ->whereIn('id', array_column($rows, 'meal_id'))->get()->keyBy('id');
        $first = substr($data['starts_at'], 0, 10);
        // Shift intervals are end-exclusive, including at midnight.
        $last = Carbon::parse($data['ends_at'])->subSecond()->format('Y-m-d');
        $errors = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $meal = $meals->get($row['meal_id']);
            if ($meal === null) {
                $errors["meals.$index.meal_id"] = __('team.scheduling.meals.errors.foreign');
            } elseif ($meal->date->format('Y-m-d') < $first || $meal->date->format('Y-m-d') > $last) {
                $errors["meals.$index.meal_id"] = __('team.scheduling.meals.errors.day');
            }
            if (in_array($row['meal_id'], $seen)) {
                $errors["meals.$index.meal_id"] = __('team.scheduling.meals.errors.duplicate');
            }
            $seen[] = $row['meal_id'];
            if (empty($row['assignment_keys'])) {
                $errors["meals.$index.assignment_keys"] = __('team.scheduling.meals.errors.empty');
            } elseif (count($row['assignment_keys']) !== count(array_unique($row['assignment_keys']))) {
                $errors["meals.$index.assignment_keys"] = __('validation.distinct', ['attribute' => __('team.scheduling.meals.people')]);
            } elseif (array_diff($row['assignment_keys'], $keys) !== []) {
                $errors["meals.$index.assignment_keys"] = __('team.scheduling.meals.errors.roster');
            }
        }

        return $errors;
    }

    public static function removalErrors(Shift $shift, int $assignmentId): array
    {
        $leavesEmpty = $shift->meals()
            ->whereHas('assignments', fn ($query) => $query->whereKey($assignmentId))
            ->whereDoesntHave('assignments', fn ($query) => $query->whereKeyNot($assignmentId))->exists();

        return $leavesEmpty ? ['meals' => __('team.scheduling.meals.errors.empty')] : [];
    }
}
