<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\InteractsWithSetup;
use App\Http\Requests\Setup\StoreEventRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    use InteractsWithSetup;

    public function show(Request $request): Response
    {
        $organization = $this->organization();
        $event = $organization->defaultEvent();

        return Inertia::render('Setup/Event', [
            'organization' => [
                'name' => $organization->name(),
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
        $organization = $this->organization();
        $data = $request->validated();

        $event = $organization->defaultEvent();

        if ($event !== null) {
            $event->ensureWritable();
        }

        if ($event === null) {
            $event = Event::query()->create($data);
        } else {
            $event->update($data);
        }

        $organization->setDefaultEvent($event);

        /** @var User $user */
        $user = $request->user();
        $user->setCurrentEvent($event);

        return redirect()->route('setup.locations');
    }
}
