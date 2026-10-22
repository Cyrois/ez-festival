<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexTeamHoursReportRequest;
use App\Http\Resources\TeamLocationHoursReportResource;
use App\Queries\TeamScheduledHoursQuery;
use App\Support\EventContext;

class LocationHoursReportController extends Controller
{
    public function index(
        IndexTeamHoursReportRequest $request,
        EventContext $eventContext,
        TeamScheduledHoursQuery $hours,
    ): TeamLocationHoursReportResource {
        $event = $eventContext->requireCurrent($request->user());
        $data = $request->validated();
        $start = $data['start_date'] ?? null;
        $end = $data['end_date'] ?? null;
        $employment = $request->employmentType();

        return new TeamLocationHoursReportResource(
            $hours->locations($event, $start, $end, $employment),
            $event->id,
            $start,
            $end,
            $employment,
            missingRateCount: $hours->locationMissingRateCount($event, $start, $end, $employment),
        );
    }
}
