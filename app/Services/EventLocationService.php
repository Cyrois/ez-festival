<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Location;
use App\Models\StockHolder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventLocationService
{
    /** @param list<array<string, mixed>> $locations */
    public function createMany(Event $event, array $locations): void
    {
        DB::transaction(function () use ($event, $locations): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            $event->locations()->createMany($locations);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(Event $event, Location $location, array $data): void
    {
        DB::transaction(function () use ($event, $location, $data): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            $location = $event->locations()->lockForUpdate()->findOrFail($location->id);
            $location->update($data);
        }, 3);
    }

    public function destroy(Event $event, Location $location): void
    {
        DB::transaction(function () use ($event, $location): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            $location = $event->locations()->lockForUpdate()->findOrFail($location->id);

            if (app(StockMovementService::class)->locationHasHistory($location)
                || $location->shifts()->exists()) {
                throw ValidationException::withMessages([
                    'location' => __('setup.locations.errors.delete_blocked'),
                ]);
            }

            StockHolder::query()->where('event_id', $event->id)->where('location_id', $location->id)->delete();
            $location->delete();
        }, 3);
    }
}
