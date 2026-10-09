<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MealAssignment;
use App\Models\Shift;
use App\Repositories\ShiftAssignmentRepository;
use App\Support\ShiftAssignmentHours;
use App\Support\ShiftAssignmentOverlaps;
use App\Support\ShiftBreaks;
use App\Support\ShiftMeals;
use App\Support\ShiftRosterChanges;
use App\Support\ShiftSlotReferences;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    public function copyDraft(Shift $shift): Shift
    {
        $repository = app(ShiftAssignmentRepository::class);
        $shift = $repository->copySource($shift);
        $others = $repository->copyOverlaps($shift);
        foreach ($shift->assignments as $assignment) {
            $rows = $others->get($assignment->team_engagement_id, collect());
            $assignment->setAttribute('copy_overlaps', ShiftAssignmentOverlaps::warnings($rows, $assignment->starts_at, $assignment->ends_at, $shift->event->timezone));
            $assignment->setAttribute('copy_other_shifts', ShiftAssignmentOverlaps::shifts($rows));
        }

        return $shift;
    }

    /** @param array<string, mixed> $data */
    public function create(Event $event, array $data): Shift
    {
        return DB::transaction(function () use ($event, $data): Shift {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            $meals = $data['meals'] ?? [];
            $slots = $data['slots'] ?? [];
            $breaks = $data['breaks'] ?? [];
            $additions = $data['assignment_additions'] ?? [];
            $supervisor = array_intersect_key($data, ['supervisor_key' => true]);
            $errors = [...ShiftBreaks::errors($data, collect()), ...ShiftRosterChanges::errors(new Shift(['event_id' => $event->id]), $data), ...ShiftMeals::errors(new Shift(['event_id' => $event->id]), $data)];
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $this->validatePersonalBreaks(new Shift($data), $data, collect(), collect());
            app(ShiftBreakService::class)->replay($data, collect(), collect());
            unset($data['break_operations']);
            unset($data['supervisor_key'], $data['meals'], $data['slots'], $data['breaks'], $data['assignment_additions'], $data['assignment_updates'], $data['assignment_removals']);
            $shift = $event->shifts()->create($data);
            $draftSlots = $this->syncSlots($shift, $slots);
            $draftBreaks = $this->syncBreaks($shift, $breaks, collect());
            $draftPeople = $this->addAssignments($shift, $additions, $draftSlots, $draftBreaks);
            $this->syncSupervisor($shift, $supervisor, $draftPeople);
            $this->syncMeals($shift, $meals, $draftPeople);

            return $shift;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Shift $shift, array $data): void
    {
        DB::transaction(function () use ($shift, $data): void {
            $event = Event::query()->lockForUpdate()->findOrFail($shift->event_id);
            $event->ensureWritable();

            $shift = $event->shifts()->lockForUpdate()->findOrFail($shift->id);
            $existingBreaks = $shift->breaks()->lockForUpdate()->get()->keyBy('id');
            $errors = [...ShiftRosterChanges::errors($shift, $data, lock: true), ...ShiftAssignmentHours::containmentErrors($shift, $data), ...ShiftBreaks::errors($data, $existingBreaks), ...ShiftMeals::errors($shift, $data)];
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $assignments = $shift->assignments()->lockForUpdate()->with('breaks')->get()->keyBy('id');
            $this->validatePersonalBreaks(new Shift([...$shift->getAttributes(), ...$data]), $data, $assignments, $existingBreaks);
            app(ShiftBreakService::class)->replay($data, $existingBreaks, $assignments);
            unset($data['break_operations']);
            $deletedSources = array_key_exists('breaks', $data) ? array_diff($existingBreaks->keys()->all(), array_column($data['breaks'], 'id')) : [];
            foreach (['assignment_updates', 'assignment_additions'] as $field) {
                foreach ($data[$field] ?? [] as $index => $row) {
                    if (isset($row['breaks'])) {
                        $data[$field][$index]['breaks'] = array_map(fn ($break) => isset($break['shift_break_id']) && in_array($break['shift_break_id'], $deletedSources) ? [...$break, 'shift_break_id' => null] : $break, $row['breaks']);
                    }
                }
            }
            $draftBreaks = [];
            $draftSlots = [];
            if (array_key_exists('slots', $data)) {
                $draftSlots = $this->syncSlots($shift, $data['slots']);
                unset($data['slots']);
            }
            if (array_key_exists('breaks', $data)) {
                $draftBreaks = $this->syncBreaks($shift, $data['breaks'], $existingBreaks);
                unset($data['breaks']);
            }
            $updates = $data['assignment_updates'] ?? [];
            $removals = $data['assignment_removals'] ?? [];
            $additions = $data['assignment_additions'] ?? [];
            $meals = $data['meals'] ?? null;
            $supervisor = array_intersect_key($data, ['supervisor_key' => true]);
            unset($data['supervisor_key'], $data['meals'], $data['assignment_updates'], $data['assignment_removals'], $data['assignment_additions']);
            $shift->update($data);
            MealAssignment::query()->whereIn('shift_assignment_id', $removals)->update(['is_active' => false]);
            $shift->assignments()->whereIn('id', $removals)->delete();
            foreach ($updates as $row) {
                [$start, $end] = ShiftAssignmentHours::resolve($shift, $row);
                $assignment = $assignments->get($row['id']);
                $assignment->update(['starts_at' => $start, 'ends_at' => $end]);
                if (array_key_exists('breaks', $row)) {
                    $assignment->setRelation('shift', $shift);
                    app(ShiftBreakService::class)->sync($assignment, $row['breaks'], $draftBreaks);
                }
            }
            $draftPeople = $this->addAssignments($shift, $additions, $draftSlots, $draftBreaks);
            $this->syncSupervisor($shift, $supervisor, $draftPeople);
            if ($meals !== null) {
                $this->syncMeals($shift, $meals, $draftPeople);
            }
            MealAssignment::query()->where('source_shift_id', $shift->id)->where('is_active', true)->whereNull('claimed_at')
                ->update(['shift_location_name' => $shift->location->name, 'shift_starts_at' => $shift->starts_at, 'shift_ends_at' => $shift->ends_at]);
        });
    }

    private function validatePersonalBreaks(Shift $proposed, array $data, Collection $assignments, Collection $sources): void
    {
        $draftKeys = array_values(array_filter(array_column($data['breaks'] ?? [], 'client_key')));
        $errors = [];
        foreach (['assignment_updates', 'assignment_additions'] as $field) {
            foreach ($data[$field] ?? [] as $index => $row) {
                [$start, $end] = ShiftAssignmentHours::resolve($proposed, $row);
                $existing = $field === 'assignment_updates' ? $assignments->get($row['id'])?->breaks->keyBy('id') ?? collect() : collect();
                $rows = $row['breaks'] ?? $existing->map(fn ($break) => [
                    'id' => $break->id, 'duration_minutes' => $break->duration_minutes,
                    'starts_at' => $break->starts_at->format('Y-m-d\TH:i'), 'shift_break_id' => $break->shift_break_id,
                ])->values()->all();
                foreach (app(ShiftBreakService::class)->errors($rows, $start, $end, $existing, $sources, $draftKeys) as $key => $message) {
                    $errors["$field.$index.$key"] = $message;
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function addAssignments(Shift $shift, array $additions, array $draftSlots, array $draftBreaks): array
    {
        $draftPeople = [];
        foreach ($additions as $index => $row) {
            try {
                if (isset($row['slot_key'])) {
                    $row['shift_role_slot_id'] = $draftSlots[$row['slot_key']] ?? null;
                    unset($row['slot_key']);
                }
                $assignment = app(ShiftAssignmentService::class)->create($shift, $row, $draftBreaks);
                if (isset($row['client_key'])) {
                    $draftPeople[$row['client_key']] = $assignment->id;
                }
            } catch (ValidationException $exception) {
                $errors = [];
                foreach ($exception->errors() as $key => $messages) {
                    $errors["assignment_additions.$index.$key"] = $messages;
                }
                throw ValidationException::withMessages($errors);
            }
        }

        return $draftPeople;
    }

    private function syncSupervisor(Shift $shift, array $data, array $draftPeople): void
    {
        if (! array_key_exists('supervisor_key', $data)) {
            return;
        }

        // Clear first so replacing the supervisor never violates the unique index.
        $shift->assignments()->where('is_supervisor', true)->update(['is_supervisor' => false]);
        if ($data['supervisor_key'] !== null) {
            $key = (int) $data['supervisor_key'];
            $shift->assignments()->whereKey($key < 0 ? $draftPeople[$key] : $key)->update(['is_supervisor' => true]);
        }
    }

    private function syncMeals(Shift $shift, array $rows, array $draftPeople): void
    {
        $existing = $shift->meals()->lockForUpdate()->get()->keyBy('meal_id');
        $kept = [];
        foreach ($rows as $row) {
            $meal = $existing->get($row['meal_id']) ?? $shift->meals()->create(['meal_id' => $row['meal_id']]);
            app(MealAssignmentService::class)->syncShiftMeal($meal, array_map(fn ($key) => $key < 0 ? $draftPeople[$key] : $key, $row['assignment_keys']));
            $kept[] = $meal->id;
        }
        MealAssignment::query()->where('source_shift_id', $shift->id)->whereNotIn('shift_meal_id', $kept)->update(['is_active' => false]);
        $shift->meals()->whereNotIn('id', $kept)->delete();
    }

    private function syncBreaks(Shift $shift, array $breaks, Collection $existing): array
    {
        $nextOrder = ($existing->max('sort_order') ?? -1) + 1;
        $kept = [];
        $draftBreaks = [];
        foreach ($breaks as $data) {
            $break = isset($data['id']) ? $existing->get((int) $data['id']) : null;
            $clientKey = $data['client_key'] ?? null;
            unset($data['id'], $data['client_key']);
            if ($break !== null) {
                $break->update($data);
            } else {
                $break = $shift->breaks()->create([...$data, 'sort_order' => $nextOrder++]);
            }
            $kept[] = $break->id;
            if ($clientKey !== null) {
                $draftBreaks[$clientKey] = $break->id;
            }
        }
        $shift->breaks()->whereNotIn('id', $kept)->delete();

        return $draftBreaks;
    }

    private function syncSlots(Shift $shift, array $slots): array
    {
        $existing = $shift->roleSlots()->lockForUpdate()->get()->keyBy('id');
        $errors = ShiftSlotReferences::errors($slots, $existing, lock: true);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $nextOrder = ($existing->max('sort_order') ?? -1) + 1;
        $kept = [];
        $draftSlots = [];
        foreach ($slots as $data) {
            $slot = isset($data['id']) ? $existing->get((int) $data['id']) : null;
            $clientKey = $data['client_key'] ?? null;
            unset($data['id'], $data['client_key']);
            if ($slot !== null) {
                if ((int) $slot->role_id !== (int) $data['role_id']) {
                    $slot->assignments()->update(['shift_role_slot_id' => null]);
                }
                $slot->update($data);
            } else {
                $slot = $shift->roleSlots()->create([...$data, 'sort_order' => $nextOrder++]);
            }
            $kept[] = $slot->id;
            if ($clientKey !== null) {
                $draftSlots[$clientKey] = $slot->id;
            }
        }
        $shift->roleSlots()->whereNotIn('id', $kept)->delete();

        return $draftSlots;
    }

    public function delete(Shift $shift, int $confirmationCount = 0): void
    {
        DB::transaction(function () use ($shift, $confirmationCount): void {
            $event = Event::query()->lockForUpdate()->findOrFail($shift->event_id);
            $event->ensureWritable();

            $shift = $event->shifts()->lockForUpdate()->findOrFail($shift->id);
            if ($shift->assignments()->count() !== $confirmationCount) {
                throw ValidationException::withMessages(['assignment_count' => __('team.scheduling.assignments.errors.stale_delete')]);
            }
            MealAssignment::query()->where('source_shift_id', $shift->id)->update(['is_active' => false]);
            $shift->delete();
        });
    }
}
