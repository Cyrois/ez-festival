<?php

namespace App\Http\Controllers\Settings\Concerns;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\User;
use App\Support\Timezones;
use Illuminate\Support\Facades\Gate;

trait InteractsWithSettings
{
    protected function locationCapabilities(Event $event): array
    {
        $canManage = Gate::allows('manage-event-locations', $event) && ! $event->isLocked();
        $isAdmin = request()->user()->isAdmin();

        return [
            'canAddLocations' => $canManage,
            'canRenameLocations' => $canManage,
            'canDeleteLocations' => $isAdmin && ! $event->isLocked(),
            'canEditLocationTypes' => $isAdmin,
        ];
    }

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
