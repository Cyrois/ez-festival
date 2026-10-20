<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexTeamHoursReportRequest;
use App\Http\Resources\TeamGroupHoursReportResource;
use App\Models\Group;
use App\Queries\TeamScheduledHoursQuery;
use App\Support\EventContext;

class GroupHoursPeopleController extends Controller
{
    public function index(
        IndexTeamHoursReportRequest $request,
        Group $group,
        EventContext $eventContext,
        TeamScheduledHoursQuery $hours,
    ): TeamGroupHoursReportResource {
        $event = $eventContext->requireCurrent($request->user());
        abort_unless((int) $group->event_id === (int) $event->id, 404);
        $data = $request->validated();
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

        return new TeamGroupHoursReportResource(
            $hours->groupRoster($event, $group->id, $startDate, $endDate),
            $event->id,
            $group,
            $startDate,
            $endDate,
        );
    }
}
