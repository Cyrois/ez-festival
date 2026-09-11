<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the authenticated dashboard scoped to the active event.
     */
    public function __invoke(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $organization = $user->primaryOrganization() ?? $user->ensureOrganization();
        $event = $organization->activeEvent;

        return Inertia::render('Dashboard', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'event' => $event
                ? [
                    'id' => $event->id,
                    'name' => $event->name,
                ]
                : null,
        ]);
    }
}
