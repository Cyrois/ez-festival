<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexSchedulingRequest;
use App\Repositories\LocationRepository;
use App\Support\EventContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SchedulingController extends Controller
{
    public function index(
        IndexSchedulingRequest $request,
        EventContext $eventContext,
        LocationRepository $locations,
    ): Response {
        $event = $eventContext->requireCurrent($request->user());
        $today = Carbon::now($event->timezone)->format('Y-m-d');
        $firstDay = $event->starts_on->format('Y-m-d');
        $lastDay = $event->ends_on->format('Y-m-d');
        $date = $request->validated()['date'] ?? ($today >= $firstDay && $today <= $lastDay ? $today : $firstDay);
        $canManage = Gate::allows('scheduling.edit');

        return Inertia::render('Team/Scheduling', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'is_locked' => $event->isLocked(),
                'timezone' => $event->timezone,
                'starts_on' => $firstDay,
                'ends_on' => $lastDay,
            ],
            'locations' => $locations->optionsFor($event),
            'canManage' => $canManage,
            'scheduleDate' => $date,
        ]);
    }
}
