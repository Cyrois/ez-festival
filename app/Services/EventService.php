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

            $entitlementItemIds = fn () => DB::table('entitlement_items')
                ->select('id')
                ->where('event_id', $event->id);
            $locationIds = fn () => DB::table('locations')
                ->select('id')
                ->where('event_id', $event->id);
            $passTypeIds = fn () => DB::table('pass_types')
                ->select('id')
                ->where('event_id', $event->id);

            DB::table('issued_entitlements')
                ->where(fn ($query) => $query
                    ->whereIn('entitlement_item_id', $entitlementItemIds())
                    ->orWhereIn('location_id', $locationIds()))
                ->delete();

            DB::table('expected_entitlements')
                ->whereIn('entitlement_item_id', $entitlementItemIds())
                ->delete();

            DB::table('entitlement_adjustments')
                ->where(fn ($query) => $query
                    ->whereIn('entitlement_item_id', $entitlementItemIds())
                    ->orWhereIn('location_id', $locationIds()))
                ->delete();

            DB::table('pass_type_entitlements')
                ->where(fn ($query) => $query
                    ->whereIn('pass_type_id', $passTypeIds())
                    ->orWhereIn('entitlement_item_id', $entitlementItemIds()))
                ->delete();

            DB::table('pass_assignments')
                ->whereIn('pass_type_id', $passTypeIds())
                ->delete();

            $event->teamEngagements()->delete();
            $event->shifts()->delete();
            $event->delete();
        });
    }
}
