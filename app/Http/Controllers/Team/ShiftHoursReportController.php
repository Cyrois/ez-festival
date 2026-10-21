<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexShiftHoursReportRequest;
use App\Http\Resources\TeamShiftHoursReportResource;
use App\Queries\TeamScheduledHoursQuery;
use App\Support\EventContext;

class ShiftHoursReportController extends Controller
{
    public function index(IndexShiftHoursReportRequest $request, EventContext $eventContext, TeamScheduledHoursQuery $hours): TeamShiftHoursReportResource
    {
        $event = $eventContext->requireCurrent($request->user());
        $day = $request->validated()['date'];

        return new TeamShiftHoursReportResource($hours->dayShiftReport($event, $day), $event->id, $day);
    }
}
