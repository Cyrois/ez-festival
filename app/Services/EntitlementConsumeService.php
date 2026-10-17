<?php

namespace App\Services;

use App\Enums\StockMovement as StockMovementType;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\IssuedEntitlement;
use App\Models\Person;
use App\Models\Role;
use App\Models\StockHolder;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\InventoryTargetsQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EntitlementConsumeService
{
    public function __construct(
        private readonly StockMovementService $stocks,
        private readonly InventoryTargetsQuery $targets,
    ) {}

    public function consume(ExpectedEntitlement $expected, User $actor, ?string $code = null): IssuedEntitlement
    {
        $eventId = $expected->passAssignment->passType->event_id;

        return DB::transaction(function () use ($expected, $actor, $code, $eventId): IssuedEntitlement {
            $event = Event::query()->lockForUpdate()->findOrFail($eventId);
            $event->ensureWritable();
            // Match inventory writes: event, engagements, roles, people, accounts,
            // then item/holder locks. Never authorize from request-cached grants.
            $members = TeamEngagement::where('event_id', $event->id)->where('person_id', $actor->person_id)
                ->orderBy('id')->lockForUpdate()->get();
            Role::whereIn('id', $members->pluck('role_id')->filter())->orderBy('id')->lockForUpdate()->get();
            Person::whereKey($actor->person_id)->lockForUpdate()->first();
            User::whereKey($actor->id)->lockForUpdate()->first();
            abort_unless(in_array('checkin.edit', $this->targets->permissions($event, $actor), true), 403);
            $expected = ExpectedEntitlement::query()
                ->with('passAssignment.passType')
                ->lockForUpdate()
                ->findOrFail($expected->id);
            abort_unless($expected->passAssignment->passType->event_id === $event->id, 404);
            $assignment = $expected->passAssignment;
            abort_unless($assignment->person_id !== null, 404);
            if ($assignment->team_engagement_id !== null) {
                abort_unless($assignment->person_id !== null && TeamEngagement::query()
                    ->whereKey($assignment->team_engagement_id)->where('event_id', $event->id)
                    ->where('status', 'hired')->where('person_id', $assignment->person_id)->exists(), 404);
            } else {
                $engagement = $assignment->artistEngagement ?? $assignment->vendorEngagement;
                abort_unless($engagement !== null && $engagement->event_id === $event->id
                    && $engagement->status === 'confirmed'
                    && $engagement->people()->where('people.id', $assignment->person_id)->exists(), 404);
            }

            if ($expected->status !== ExpectedEntitlement::STATUS_EXPECTED || $expected->issuedEntitlement()->exists()) {
                throw ValidationException::withMessages([
                    'expected_entitlement' => __('credentials.entitlements.errors.already_consumed'),
                ]);
            }

            $item = $event->entitlementItems()->lockForUpdate()->findOrFail($expected->entitlement_item_id);
            $holder = StockHolder::where('event_id', $event->id)->where('person_id', $actor->person_id)
                ->lockForUpdate()->first();
            if ($holder === null || $this->stocks->balance($item, $holder) < 1) {
                throw ValidationException::withMessages(['quantity' => __('check_in.no_stock')]);
            }
            $code = $code === null ? null : trim($code);
            $code = $code === '' ? null : $code;
            Validator::make(['code' => $code], ['code' => ['nullable', 'string', 'max:64']])->validate();

            $issued = $expected->issuedEntitlement()->create([
                'entitlement_item_id' => $item->id,
                'code' => $code,
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ]);
            $expected->update(['status' => ExpectedEntitlement::STATUS_CONSUMED]);
            $this->stocks->move($item, StockMovementType::GiveOut, 1, $holder, null, $actor, issued: $issued);

            return $issued;
        }, 3);
    }
}
