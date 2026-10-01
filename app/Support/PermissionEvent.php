<?php

namespace App\Support;

use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\PassAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class PermissionEvent
{
    public function resolve(Request $request): ?Event
    {
        foreach ($request->route()?->parameters() ?? [] as $record) {
            if ($record instanceof Event) {
                return $record;
            }
            if ($record instanceof ExpectedEntitlement) {
                return $record->entitlementItem?->event;
            }
            if ($record instanceof PassAssignment) {
                return $record->passType?->event;
            }
            if ($record instanceof Model && $record->getAttribute('event_id') !== null) {
                return $record->event;
            }
        }

        return $request->user()?->effectiveEvent();
    }
}
