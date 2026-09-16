<?php

namespace App\Http\Controllers\Settings\Concerns;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\User;
use App\Support\Timezones;

trait InteractsWithSettings
{
    /**
     * @return list<string>
     */
    protected function timezones(): array
    {
        return Timezones::common();
    }

    /**
     * @return array<string, mixed>
     */
    protected function eventPayload(Event $event): array
    {
        /** @var User $user */
        $user = request()->user();
        $primaryEventId = $user?->effectiveEvent()?->id;

        return EventResource::toArray($event, $primaryEventId);
    }
}
