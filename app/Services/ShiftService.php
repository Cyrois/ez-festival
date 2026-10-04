<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Shift;
use App\Support\ShiftAssignmentHours;
use App\Support\ShiftBreaks;
use App\Support\ShiftRosterChanges;
use App\Support\ShiftSlotReferences;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    /** @param array<string, mixed> $data */
    public function create(Event $event, array $data): Shift
    {
        return DB::transaction(function () use ($event, $data): Shift {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            $slots = $data['slots'] ?? [];
            $breaks = $data['breaks'] ?? [];
            $errors = ShiftBreaks::errors($data, collect());
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            unset($data['slots'], $data['breaks']);
            $shift = $event->shifts()->create($data);
            $this->syncSlots($shift, $slots);
            $this->syncBreaks($shift, $breaks, collect());

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
            $errors = [...ShiftRosterChanges::errors($shift, $data, lock: true), ...ShiftAssignmentHours::containmentErrors($shift, $data), ...ShiftBreaks::errors($data, $existingBreaks)];
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $draftSlots = [];
            if (array_key_exists('slots', $data)) {
                $draftSlots = $this->syncSlots($shift, $data['slots']);
                unset($data['slots']);
            }
            if (array_key_exists('breaks', $data)) {
                $this->syncBreaks($shift, $data['breaks'], $existingBreaks);
                unset($data['breaks']);
            }
            $updates = $data['assignment_updates'] ?? [];
            $removals = $data['assignment_removals'] ?? [];
            $additions = $data['assignment_additions'] ?? [];
            unset($data['assignment_updates'], $data['assignment_removals'], $data['assignment_additions']);
            $shift->update($data);
            $shift->assignments()->whereIn('id', $removals)->delete();
            foreach ($updates as $row) {
                [$start, $end] = ShiftAssignmentHours::resolve($shift, $row);
                $shift->assignments()->whereKey($row['id'])->update(['starts_at' => $start, 'ends_at' => $end]);
            }
            foreach ($additions as $index => $row) {
                try {
                    if (isset($row['slot_key'])) {
                        $row['shift_role_slot_id'] = $draftSlots[$row['slot_key']] ?? null;
                        unset($row['slot_key']);
                    }
                    app(ShiftAssignmentService::class)->create($shift, $row);
                } catch (ValidationException $exception) {
                    $errors = [];
                    foreach ($exception->errors() as $key => $messages) {
                        $errors["assignment_additions.$index.$key"] = $messages;
                    }
                    throw ValidationException::withMessages($errors);
                }
            }
        });
    }

    private function syncBreaks(Shift $shift, array $breaks, Collection $existing): void
    {
        $nextOrder = ($existing->max('sort_order') ?? -1) + 1;
        $kept = [];
        foreach ($breaks as $data) {
            $break = isset($data['id']) ? $existing->get((int) $data['id']) : null;
            unset($data['id']);
            if ($break !== null) {
                $break->update($data);
            } else {
                $break = $shift->breaks()->create([...$data, 'sort_order' => $nextOrder++]);
            }
            $kept[] = $break->id;
        }
        $shift->breaks()->whereNotIn('id', $kept)->delete();
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
            $shift->delete();
        });
    }
}
