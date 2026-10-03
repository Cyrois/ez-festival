<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\IndexScheduleGridRequest;
use App\Http\Resources\ScheduleLocationResource;
use App\Repositories\ShiftRepository;
use App\Support\EventContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduleGridController extends Controller
{
    public function index(
        IndexScheduleGridRequest $request,
        EventContext $eventContext,
        ShiftRepository $shifts,
    ): AnonymousResourceCollection {
        $event = $eventContext->requireCurrent($request->user());
        $data = $request->validated();

        return ScheduleLocationResource::collection($shifts->schedule($event, $data['date'], (int) ($data['page'] ?? 1)))
            ->additional(['schedule' => [
                'date' => $data['date'],
                'shift_count' => $shifts->countForDay($event, $data['date']),
                'first_shift_minute' => $shifts->firstShiftMinuteForDay($event, $data['date']),
            ]]);
    }
}
