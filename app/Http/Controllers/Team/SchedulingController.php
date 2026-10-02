<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Repositories\LocationRepository;
use App\Support\EventContext;
use App\Support\ShiftSlotReferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SchedulingController extends Controller
{
    public function index(
        Request $request,
        EventContext $eventContext,
        LocationRepository $locations,
    ): Response {
        Gate::authorize('scheduling.view');

        $event = $eventContext->requireCurrent($request->user());

        return Inertia::render('Team/Scheduling', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'is_locked' => $event->isLocked(),
            ],
            'locations' => $locations->optionsFor($event),
            'canManage' => Gate::allows('scheduling.edit'),
            'roles' => Gate::allows('scheduling.edit') && ! $event->isLocked() ? ShiftSlotReferences::options() : [],
        ]);
    }
}
