<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\IssuedEntitlement;
use App\Models\TeamEngagement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntitlementConsumeService
{
    public function __construct(private readonly EntitlementItemService $items) {}

    public function consume(ExpectedEntitlement $expected, User $actor, int $locationId, ?string $code = null): IssuedEntitlement
    {
        $eventId = $expected->passAssignment->passType->event_id;

        return DB::transaction(function () use ($expected, $actor, $locationId, $code, $eventId): IssuedEntitlement {
            $event = Event::query()->lockForUpdate()->findOrFail($eventId);
            $event->ensureWritable();
            $expected = ExpectedEntitlement::query()
                ->with('passAssignment.passType')
                ->lockForUpdate()
                ->findOrFail($expected->id);
            abort_unless($expected->passAssignment->passType->event_id === $event->id, 404);
            $assignment = $expected->passAssignment;
            if ($assignment->team_engagement_id !== null) {
                abort_unless($assignment->person_id !== null && TeamEngagement::query()
                    ->whereKey($assignment->team_engagement_id)->where('event_id', $event->id)
                    ->where('status', 'hired')->where('person_id', $assignment->person_id)->exists(), 404);
            }

            if ($expected->status !== ExpectedEntitlement::STATUS_EXPECTED || $expected->issuedEntitlement()->exists()) {
                throw ValidationException::withMessages([
                    'expected_entitlement' => __('credentials.entitlements.errors.already_consumed'),
                ]);
            }

            $item = $event->entitlementItems()->lockForUpdate()->findOrFail($expected->entitlement_item_id);

            $issued = $expected->issuedEntitlement()->create([
                'entitlement_item_id' => $item->id,
                'location_id' => $locationId,
                'code' => $code,
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ]);
            $expected->update(['status' => ExpectedEntitlement::STATUS_CONSUMED]);
            $this->items->adjust($item, $locationId, -1, null, $actor);

            return $issued;
        }, 3);
    }
}
