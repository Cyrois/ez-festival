<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexTeamHoursReportRequest;
use App\Http\Resources\TeamLocationHoursReportResource;
use App\Models\Location;
use App\Queries\TeamScheduledHoursQuery;
use App\Support\EventContext;
use Illuminate\Support\Facades\Gate;

class LocationHoursPeopleController extends Controller
{
    public function index(
        IndexTeamHoursReportRequest $request,
        Location $location,
        EventContext $eventContext,
        TeamScheduledHoursQuery $hours,
    ): TeamLocationHoursReportResource {
        $event = $eventContext->requireCurrent($request->user());
        abort_unless((int) $location->event_id === (int) $event->id, 404);
        $data = $request->validated();
        $start = $data['start_date'] ?? null;
        $end = $data['end_date'] ?? null;
        $employment = $request->employmentType();

        return new TeamLocationHoursReportResource(
            $hours->locationPeople($event, $location->id, $start, $end, $employment),
            $event->id,
            $start,
            $end,
            $employment,
            $location,
            Gate::allows('team.view', $event),
        );
    }
}
