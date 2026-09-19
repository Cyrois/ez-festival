<?php

namespace App\Services;

use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassAssignment;
use App\Models\Person;
use App\Models\VendorEngagement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PassAssignmentService
{
    public function give(ArtistEngagement|VendorEngagement $engagement, Pass $pass, int $quantity): void
    {
        DB::transaction(function () use ($engagement, $pass, $quantity): void {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();
            $pass = Pass::query()->lockForUpdate()->findOrFail($pass->id);

            abort_unless($pass->event_id === $event->id, 404);

            $count = $pass->assignments()->count();
            if ($pass->max_assignments !== null && $count + $quantity > $pass->max_assignments) {
                throw ValidationException::withMessages([
                    'quantity' => __('credentials.assignments.errors.capacity'),
                ]);
            }

            $engagement->passAssignments()->createMany(array_fill(0, $quantity, [
                'pass_id' => $pass->id,
            ]));
        });
    }

    public function assign(PassAssignment $assignment, Person $person): void
    {
        DB::transaction(function () use ($assignment, $person): void {
            $assignment->loadMissing('assignable.event');
            $assignable = $assignment->assignable;
            abort_unless($assignable instanceof ArtistEngagement || $assignable instanceof VendorEngagement, 404);

            $event = Event::query()->lockForUpdate()->findOrFail($assignable->event_id);
            $event->ensureWritable();
            abort_unless($assignable->people()->whereKey($person->id)->exists(), 422);

            $assignment->update(['person_id' => $person->id]);
        });
    }

    public function remove(PassAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            $assignment->loadMissing('assignable.event');
            $assignable = $assignment->assignable;
            abort_unless($assignable instanceof ArtistEngagement || $assignable instanceof VendorEngagement, 404);

            $event = Event::query()->lockForUpdate()->findOrFail($assignable->event_id);
            $event->ensureWritable();
            $assignment->delete();
        });
    }
}
