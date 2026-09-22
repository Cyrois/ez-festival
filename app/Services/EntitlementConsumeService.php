<?php

namespace App\Services;

use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\IssuedEntitlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntitlementConsumeService
{
    public function __construct(private readonly EntitlementItemService $items) {}

    public function consume(ExpectedEntitlement $expected, User $actor, int $locationId, ?string $code = null): IssuedEntitlement
    {
        return DB::transaction(function () use ($expected, $actor, $locationId, $code): IssuedEntitlement {
            $expected = ExpectedEntitlement::query()
                ->with('passAssignment.passType')
                ->lockForUpdate()
                ->findOrFail($expected->id);
            $event = Event::query()->lockForUpdate()->findOrFail($expected->passAssignment->passType->event_id);
            $event->ensureWritable();

            if ($expected->status !== ExpectedEntitlement::STATUS_EXPECTED || $expected->issuedEntitlement()->exists()) {
                throw ValidationException::withMessages([
                    'expected_entitlement' => __('credentials.entitlements.errors.already_consumed'),
                ]);
            }

            $item = EntitlementItem::query()->lockForUpdate()->findOrFail($expected->entitlement_item_id);

            $issued = $expected->issuedEntitlement()->create([
                'entitlement_item_id' => $item->id,
                'code' => $code,
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ]);
            $expected->update(['status' => ExpectedEntitlement::STATUS_CONSUMED]);
            $this->items->adjust($item, $locationId, -1, null, $actor);

            return $issued;
        });
    }
}
