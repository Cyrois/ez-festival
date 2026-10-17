<?php

namespace App\Services;

use App\Enums\StockMovement as StockMovementType;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\IssuedEntitlement;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Support\EntitlementCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EntitlementConsumeService
{
    public function __construct(private readonly StockMovementService $stocks) {}

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
            $code = $code === null ? null : trim($code);
            $code = $code === '' ? null : $code;
            Validator::make(['code' => $code], [
                'code' => [$item->needs_unique_code ? 'required' : 'nullable', 'string', 'max:64'],
            ])->validate();

            // Optional codes also reserve their value for subsequent coded
            // check-ins. Their existing ability to repeat is unchanged.
            if ($code !== null && ! EntitlementCode::reserve($event->id, $code) && $item->needs_unique_code) {
                throw ValidationException::withMessages(['code' => __('check_in.errors.code_used')]);
            }

            $issued = $expected->issuedEntitlement()->create([
                'entitlement_item_id' => $item->id,
                'location_id' => $locationId,
                'code' => $code,
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ]);
            $expected->update(['status' => ExpectedEntitlement::STATUS_CONSUMED]);
            $holder = $this->stocks->forLocation($event, $locationId);
            $this->stocks->move($item, StockMovementType::GiveOut, 1, $holder, null, $actor, issued: $issued);

            return $issued;
        }, 3);
    }
}
