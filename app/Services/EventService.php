<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function delete(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            // shifts.location_id restricts location deletes, and InnoDB checks it while the
            // event cascade removes locations, so the event's shifts must go first.
            $event->shifts()->delete();
            $event->delete();
        });
    }
}
