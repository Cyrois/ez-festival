<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexTeamReportsRequest;
use App\Repositories\GroupRepository;
use App\Repositories\LocationRepository;
use App\Support\EventContext;
use App\Support\TeamHoursReportFilter;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(IndexTeamReportsRequest $request, EventContext $eventContext, GroupRepository $groups, LocationRepository $locations): Response
    {
        $event = $eventContext->requireCurrent($request->user());
        $data = $request->validated();

        return Inertia::render('Team/Reports', [
            'shiftFilters' => [
                'view' => $data['view'] ?? (isset($data['group_id']) ? 'team' : 'summary'),
                'date' => $data['date'] ?? Carbon::now($event->timezone)->format('Y-m-d'),
            ],
            'filters' => [
                'employment_type' => $request->employmentType(),
                'location_id' => isset($data['location_id']) ? (int) $data['location_id'] : null,
                'group_id' => isset($data['group_id']) ? (int) $data['group_id'] : null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
            ],
            'employmentOptions' => TeamHoursReportFilter::options(),
            'locationOptions' => $locations->optionsFor($event)->map(fn ($location): array => ['location_id' => $location->id, 'name' => $location->name]),
            'groupOptions' => $groups->optionsFor($event)->map(fn ($group): array => ['group_id' => $group->id, 'name' => $group->name]),
        ]);
    }
}
