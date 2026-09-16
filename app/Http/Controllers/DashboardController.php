<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the authenticated dashboard scoped to the effective event.
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $event = $user->effectiveEvent();

        return Inertia::render('Dashboard', [
            'event' => $event
                ? [
                    'id' => $event->id,
                    'name' => $event->name,
                ]
                : null,
        ]);
    }
}
