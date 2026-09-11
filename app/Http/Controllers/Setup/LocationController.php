<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\InteractsWithSetup;
use App\Http\Requests\Setup\StoreLocationRequest;
use App\Http\Requests\Setup\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    use InteractsWithSetup;

    public function show(Request $request): Response|RedirectResponse
    {
        $organization = $this->organization($request);
        $event = $organization->activeEvent;

        if ($event === null) {
            return redirect()->route('setup.event');
        }

        return Inertia::render('Setup/Locations', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
            ],
            'locations' => $event->locations()
                ->orderBy('id')
                ->get(['id', 'name', 'type']),
            'currentStep' => 2,
        ]);
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $event = $organization->activeEvent;

        if ($event === null) {
            return redirect()->route('setup.event');
        }

        $event->locations()->create($request->validated());

        return redirect()->route('setup.locations');
    }

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $organization = $this->organization($request);
        $event = $organization->activeEvent;

        abort_unless(
            $event !== null && $location->event_id === $event->id,
            404,
        );

        $location->update($request->validated());

        return redirect()->route('setup.locations');
    }

    public function continue(Request $request): RedirectResponse
    {
        $this->organization($request);

        return redirect()->route('setup.vendor-types');
    }

    public function skip(Request $request): RedirectResponse
    {
        $this->organization($request);

        return redirect()->route('setup.vendor-types');
    }
}
