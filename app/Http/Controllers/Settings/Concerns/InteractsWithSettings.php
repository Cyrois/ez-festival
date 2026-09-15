<?php

namespace App\Http\Controllers\Settings\Concerns;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Support\Timezones;
use Illuminate\Http\Request;

trait InteractsWithSettings
{
    protected function organization(Request $request): Organization
    {
        /** @var User $user */
        $user = $request->user();

        return $user->primaryOrganization() ?? $user->ensureOrganization();
    }

    protected function eventForOrganization(Request $request, Event $event): Event
    {
        $organization = $this->organization($request);

        abort_unless((int) $event->organization_id === (int) $organization->id, 404);

        return $event;
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
    protected function eventPayload(Event $event, Organization $organization): array
    {
        return EventResource::toArray($event, $organization->active_event_id);
    }
}
