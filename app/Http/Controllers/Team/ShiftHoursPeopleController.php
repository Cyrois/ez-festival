<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexShiftHoursPeopleRequest;
use App\Http\Resources\TeamShiftHoursPeopleResource;
use App\Models\Shift;
use App\Queries\TeamScheduledHoursQuery;
use App\Support\EventContext;
use Illuminate\Support\Facades\Gate;

class ShiftHoursPeopleController extends Controller
{
    public function index(IndexShiftHoursPeopleRequest $request, Shift $shift, EventContext $eventContext, TeamScheduledHoursQuery $hours): TeamShiftHoursPeopleResource
    {
        $event = $eventContext->requireCurrent($request->user());
        abort_unless((int) $shift->event_id === (int) $event->id, 404);

        return new TeamShiftHoursPeopleResource($hours->shiftPeople($event, $shift->id), $event->id, $shift->id, Gate::allows('team.view', $event));
    }
}
