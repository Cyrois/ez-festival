<?php

namespace App\Services;

use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\EventPatron;
use App\Models\ExpectedEntitlement;
use App\Models\PassAssignment;
use App\Models\PassType;
use App\Models\Person;
use App\Models\VendorEngagement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PassAssignmentService
{
    public function give(ArtistEngagement|VendorEngagement|EventPatron $owner, PassType $passType, int $quantity): void
    {
        DB::transaction(function () use ($owner, $passType, $quantity): void {
            $event = Event::query()->lockForUpdate()->findOrFail($owner->event_id);
            $event->ensureWritable();
            $passType = PassType::query()->lockForUpdate()->with('entitlements')->findOrFail($passType->id);
            abort_unless($passType->event_id === $event->id, 404);

            if ($passType->max_assignments !== null
                && $passType->assignments()->count() + $quantity > $passType->max_assignments) {
                throw ValidationException::withMessages([
                    'quantity' => __('credentials.assignments.errors.capacity'),
                ]);
            }

            $assignments = $owner->passAssignments()->createMany(array_fill(0, $quantity, [
                'pass_type_id' => $passType->id,
            ]));

            foreach ($assignments as $assignment) {
                $assignment->expectedEntitlements()->createMany(
                    $passType->entitlements->map(fn ($line): array => [
                        'entitlement_item_id' => $line->entitlement_item_id,
                        'status' => ExpectedEntitlement::STATUS_EXPECTED,
                    ])->all(),
                );
            }
        });
    }

    public function assignPerson(PassAssignment $assignment, Person $person): void
    {
        DB::transaction(function () use ($assignment, $person): void {
            $assignment = PassAssignment::query()
                ->with(['artistEngagement.people', 'vendorEngagement.people', 'eventPatron'])
                ->lockForUpdate()
                ->findOrFail($assignment->id);
            $owner = $this->owner($assignment);
            $event = Event::query()->lockForUpdate()->findOrFail($owner->event_id);
            $event->ensureWritable();

            if ($owner instanceof ArtistEngagement || $owner instanceof VendorEngagement) {
                abort_unless($owner->people()->whereKey($person->id)->exists(), 422);
            } elseif ($owner instanceof EventPatron) {
                abort_unless((int) $owner->person_id === (int) $person->id, 422);
            }

            $assignment->update(['person_id' => $person->id]);
        });
    }

    public function remove(PassAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            $assignment = PassAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $owner = $this->owner($assignment);
            $event = Event::query()->lockForUpdate()->findOrFail($owner->event_id);
            $event->ensureWritable();

            if ($assignment->expectedEntitlements()->whereHas('issuedEntitlement')->exists()) {
                throw ValidationException::withMessages([
                    'assignment' => __('credentials.assignments.errors.remove_issued'),
                ]);
            }

            $assignment->delete();
        });
    }

    /** @return HasMany<PassAssignment, Model> */
    public function listForOwner(ArtistEngagement|VendorEngagement|EventPatron $owner)
    {
        return $owner->passAssignments()->with(['passType.labels', 'person']);
    }

    private function owner(PassAssignment $assignment): ArtistEngagement|VendorEngagement|EventPatron
    {
        $assignment->loadMissing(['artistEngagement', 'vendorEngagement', 'eventPatron']);
        $owners = array_filter([
            $assignment->artistEngagement,
            $assignment->vendorEngagement,
            $assignment->eventPatron,
        ]);

        abort_unless(count($owners) === 1, 422);

        return array_values($owners)[0];
    }
}
