<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexScheduleRosterRequest;
use App\Http\Resources\ShiftResource;
use App\Repositories\ShiftRepository;
use App\Support\EventContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduleRosterController extends Controller
{
    public function index(IndexScheduleRosterRequest $request, EventContext $eventContext, ShiftRepository $shifts): AnonymousResourceCollection
    {
        $event = $eventContext->requireCurrent($request->user());
        $data = $request->validated();
        $locationId = (int) $data['location_id'];

        return ShiftResource::collection($shifts->scheduleRosters($event, $data['date'], $locationId, (int) ($data['page'] ?? 1)))
            ->additional(['schedule' => [
                'date' => $data['date'],
                'first_shift_minute' => $shifts->firstShiftMinuteForDay($event, $data['date'], $locationId),
            ]]);
    }
}
