<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\InteractsWithSetup;
use App\Http\Requests\Setup\StoreEventRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    use InteractsWithSetup;

    public function show(Request $request): Response
    {
        $organization = $this->organization($request);
        $event = $organization->activeEvent;

        return Inertia::render('Setup/Event', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'event' => $event ? [
                'id' => $event->id,
                'name' => $event->name,
                'starts_on' => $event->starts_on->toDateString(),
                'ends_on' => $event->ends_on->toDateString(),
                'timezone' => $event->timezone,
            ] : null,
            'timezones' => $this->timezones(),
            'currentStep' => 1,
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validated();

        $event = $organization->activeEvent;

        if ($event === null) {
            $event = Event::query()->create([
                'organization_id' => $organization->id,
                ...$data,
            ]);
        } else {
            $event->update($data);
        }

        $organization->forceFill(['active_event_id' => $event->id])->save();

        return redirect()->route('setup.locations');
    }

    public function skip(Request $request): RedirectResponse
    {
        $this->organization($request);

        return redirect()->route('setup.locations');
    }
}
