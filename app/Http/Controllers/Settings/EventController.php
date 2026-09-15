<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\InteractsWithSettings;
use App\Http\Requests\Settings\SetPrimaryEventRequest;
use App\Http\Requests\Settings\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    use InteractsWithSettings;

    public function index(Request $request): Response
    {
        $organization = $this->organization($request);

        /** @var User $user */
        $user = $request->user();
        $primaryEventId = $user->effectiveEvent($organization)?->id;

        $events = $organization->events()
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Event $event) => EventResource::toArray($event, $primaryEventId));

        return Inertia::render('Settings/Events/Index', [
            'events' => $events,
        ]);
    }

    public function edit(Request $request, Event $event): Response
    {
        $organization = $this->organization($request);
        $this->eventForOrganization($request, $event);

        return Inertia::render('Settings/Events/Edit', [
            'event' => $this->eventPayload($event, $organization),
            'timezones' => $this->timezones(),
            'tab' => 'details',
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $organization = $this->organization($request);
        $this->eventForOrganization($request, $event);

        $event->ensureWritable($organization);
        $event->update($request->validated());

        return redirect()
            ->route('settings.events.index')
            ->with('success', __('settings.events.toast.updated'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function setPrimary(SetPrimaryEventRequest $request, Event $event): RedirectResponse
    {
        $organization = $request->organization();
        $this->eventForOrganization($request, $event);

        /** @var User $user */
        $user = $request->user();
        $user->setCurrentEvent($organization, $event);

        return redirect()
            ->route('settings.events.index')
            ->with('success', __('settings.events.toast.set_primary', ['name' => $event->name]))
            ->with('success_title', __('settings.events.toast.set_primary_title'));
    }

    public function roles(Request $request, Event $event): Response
    {
        $organization = $this->organization($request);
        $this->eventForOrganization($request, $event);

        return Inertia::render('Settings/Events/Roles', [
            'event' => $this->eventPayload($event, $organization),
            'tab' => 'roles',
        ]);
    }

    public function users(Request $request, Event $event): Response
    {
        $organization = $this->organization($request);
        $this->eventForOrganization($request, $event);

        return Inertia::render('Settings/Events/Users', [
            'event' => $this->eventPayload($event, $organization),
            'tab' => 'users',
        ]);
    }
}
