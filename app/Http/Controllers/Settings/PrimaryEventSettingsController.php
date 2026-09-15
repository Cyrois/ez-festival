<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\InteractsWithSettings;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrimaryEventSettingsController extends Controller
{
    use InteractsWithSettings;

    public function locations(Request $request): Response|RedirectResponse
    {
        $event = $this->primaryEventOrRedirect($request);

        if ($event instanceof RedirectResponse) {
            return $event;
        }

        $organization = $this->organization($request);

        return Inertia::render('Settings/Events/Locations', [
            'event' => $this->eventPayload($event, $organization),
            'locations' => $event->locations()
                ->orderBy('id')
                ->get(['id', 'name', 'type']),
            'tab' => 'locations',
            'forPrimary' => true,
        ]);
    }

    public function roles(Request $request): Response|RedirectResponse
    {
        $event = $this->primaryEventOrRedirect($request);

        if ($event instanceof RedirectResponse) {
            return $event;
        }

        $organization = $this->organization($request);

        return Inertia::render('Settings/Events/Roles', [
            'event' => $this->eventPayload($event, $organization),
            'tab' => 'roles',
            'forPrimary' => true,
        ]);
    }

    public function users(Request $request): Response|RedirectResponse
    {
        $event = $this->primaryEventOrRedirect($request);

        if ($event instanceof RedirectResponse) {
            return $event;
        }

        $organization = $this->organization($request);

        return Inertia::render('Settings/Events/Users', [
            'event' => $this->eventPayload($event, $organization),
            'tab' => 'users',
            'forPrimary' => true,
        ]);
    }

    private function primaryEventOrRedirect(Request $request): Event|RedirectResponse
    {
        $organization = $this->organization($request);

        /** @var User $user */
        $user = $request->user();
        $event = $user->effectiveEvent($organization);

        if ($event === null) {
            return redirect()
                ->route('settings.events.index')
                ->with('error', __('settings.events.primary_missing'));
        }

        return $event;
    }
}
