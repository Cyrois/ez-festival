<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\InteractsWithSetup;
use App\Http\Requests\Setup\ContinueLocationsRequest;
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
        $client = $this->client();
        $event = $client->defaultEvent();

        if ($event === null) {
            return redirect()->route('setup.event');
        }

        return Inertia::render('Setup/Locations', [
            'client' => [
                'name' => $client->name(),
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
        $event = $this->client()->defaultEvent();

        if ($event === null) {
            return redirect()->route('setup.event');
        }

        $event->ensureWritable();

        $event->locations()->create($request->validated());

        return redirect()->route('setup.locations');
    }

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $event = $this->client()->defaultEvent();

        abort_unless(
            $event !== null && $location->event_id === $event->id,
            404,
        );

        $event->ensureWritable();

        $location->update($request->validated());

        return redirect()->route('setup.locations');
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        $event = $this->client()->defaultEvent();

        abort_unless(
            $event !== null && $location->event_id === $event->id,
            404,
        );

        $event->ensureWritable();

        $location->delete();

        return redirect()->route('setup.locations');
    }

    public function continue(ContinueLocationsRequest $request): RedirectResponse
    {
        $event = $this->client()->defaultEvent();

        if ($event === null) {
            return redirect()->route('setup.event');
        }

        $event->ensureWritable();

        $data = $request->validated();

        foreach ($data['suggestions'] ?? [] as $item) {
            $event->locations()->create([
                'name' => $item['name'],
                'type' => $item['type'] ?? null,
            ]);
        }

        return redirect()->route('setup.vendor-types');
    }

    public function skip(Request $request): RedirectResponse
    {
        return redirect()->route('setup.vendor-types');
    }
}
