<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexScheduleRosterRequest;
use App\Http\Resources\ShiftResource;
use App\Repositories\ShiftAssignmentRepository;
use App\Repositories\ShiftRepository;
use App\Support\EventContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduleRosterController extends Controller
{
    public function index(IndexScheduleRosterRequest $request, EventContext $eventContext, ShiftRepository $shifts, ShiftAssignmentRepository $assignments): AnonymousResourceCollection
    {
        $event = $eventContext->requireCurrent($request->user());
        $data = $request->validated();
        $locationId = (int) $data['location_id'];

        $rosters = $shifts->scheduleRosters($event, $data['date'], $locationId);
        $assignments->loadRosters($rosters, $event);

        return ShiftResource::collection($rosters)
            ->additional(['schedule' => [
                'date' => $data['date'],
                'first_shift_minute' => $shifts->firstShiftMinuteForDay($event, $data['date'], $locationId),
            ]]);
    }
}
