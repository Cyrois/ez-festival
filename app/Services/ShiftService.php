<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    /** @param array<string, mixed> $data */
    public function create(Event $event, array $data): Shift
    {
        return DB::transaction(function () use ($event, $data): Shift {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            return $event->shifts()->create($data);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Shift $shift, array $data): void
    {
        DB::transaction(function () use ($shift, $data): void {
            $event = Event::query()->lockForUpdate()->findOrFail($shift->event_id);
            $event->ensureWritable();

            Shift::query()->lockForUpdate()->findOrFail($shift->id)->update($data);
        });
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
