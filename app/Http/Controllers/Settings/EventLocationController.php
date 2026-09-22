<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\InteractsWithSettings;
use App\Http\Requests\Settings\DestroyEventLocationRequest;
use App\Http\Requests\Settings\StoreEventLocationRequest;
use App\Http\Requests\Settings\UpdateEventLocationRequest;
use App\Models\Event;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventLocationController extends Controller
{
    use InteractsWithSettings;

    public function index(Request $request, Event $event): Response
    {
        return Inertia::render('Settings/Events/Locations', [
            'event' => $this->eventPayload($event),
            'locations' => $event->locations()
                ->orderBy('id')
                ->get(['id', 'name', 'type']),
            'tab' => 'locations',
        ]);
    }

    public function store(StoreEventLocationRequest $request, Event $event): RedirectResponse
    {
        $event->ensureWritable();

        $event->locations()->create($request->validated());

        return redirect()
            ->route('settings.events.locations', $event)
            ->with('success', __('setup.toast.location_added'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function update(
        UpdateEventLocationRequest $request,
        Event $event,
        Location $location,
    ): RedirectResponse {
        abort_unless((int) $location->event_id === (int) $event->id, 404);

        $event->ensureWritable();
        $location->update($request->validated());

        return redirect()
            ->route('settings.events.locations', $event)
            ->with('success', __('setup.toast.location_updated'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function destroy(DestroyEventLocationRequest $request, Event $event, Location $location): RedirectResponse
    {
        abort_unless((int) $location->event_id === (int) $event->id, 404);

        $event->ensureWritable();
        $location->delete();

        return redirect()
            ->route('settings.events.locations', $event)
            ->with('success', __('setup.toast.location_deleted'))
            ->with('success_title', __('toast.saved_title'));
    }
}
