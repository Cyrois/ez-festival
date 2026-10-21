<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexTeamHoursReportRequest;
use App\Http\Resources\TeamHoursReportResource;
use App\Queries\TeamScheduledHoursQuery;
use App\Support\EventContext;

class HoursReportController extends Controller
{
    public function index(
        IndexTeamHoursReportRequest $request,
        EventContext $eventContext,
        TeamScheduledHoursQuery $hours,
    ): TeamHoursReportResource {
        $event = $eventContext->requireCurrent($request->user());
        $data = $request->validated();
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

        return new TeamHoursReportResource(
            $hours->groups($event, $startDate, $endDate, $request->employmentType()),
            $event->id,
            $startDate,
            $endDate,
            $request->employmentType(),
        );
    }
}
