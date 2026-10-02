<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Shift;
use App\Support\ShiftSlotReferences;
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
            unset($data['slots']);
            $shift = $event->shifts()->create($data);
            $this->syncSlots($shift, $slots);

            return $shift;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Shift $shift, array $data): void
    {
        DB::transaction(function () use ($shift, $data): void {
            $event = Event::query()->lockForUpdate()->findOrFail($shift->event_id);
            $event->ensureWritable();

            $shift = Shift::query()->lockForUpdate()->findOrFail($shift->id);
            if (array_key_exists('slots', $data)) {
                $this->syncSlots($shift, $data['slots']);
                unset($data['slots']);
            }
            $shift->update($data);
        });
    }

    private function syncSlots(Shift $shift, array $slots): void
    {
        $existing = $shift->roleSlots()->lockForUpdate()->get()->keyBy('id');
        $errors = ShiftSlotReferences::errors($slots, $existing, lock: true);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $nextOrder = ($existing->max('sort_order') ?? -1) + 1;
        $kept = [];
        foreach ($slots as $data) {
            $slot = isset($data['id']) ? $existing->get((int) $data['id']) : null;
            unset($data['id']);
            $data['is_supervisor'] ??= false;
            if ($slot !== null) {
                $slot->update($data);
            } else {
                $slot = $shift->roleSlots()->create([...$data, 'sort_order' => $nextOrder++]);
            }
            $kept[] = $slot->id;
        }
        $shift->roleSlots()->whereNotIn('id', $kept)->delete();
    }

    public function delete(Shift $shift): void
    {
        DB::transaction(function () use ($shift): void {
            $event = Event::query()->lockForUpdate()->findOrFail($shift->event_id);
            $event->ensureWritable();

            Shift::query()->lockForUpdate()->findOrFail($shift->id)->delete();
        });
    }
}
