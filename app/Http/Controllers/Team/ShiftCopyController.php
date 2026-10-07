<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\CreateShiftRequest;
use App\Http\Resources\ShiftCopyResource;
use App\Models\Shift;
use App\Repositories\LocationRepository;
use App\Services\ShiftService;
use App\Support\EventContext;
use App\Support\LabelColors;
use App\Support\ShiftBreaks;
use App\Support\ShiftReturnContext;
use App\Support\ShiftSlotReferences;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShiftCopyController extends Controller
{
    public function create(CreateShiftRequest $request, Shift $shift, EventContext $context, ShiftService $service, LocationRepository $locations): Response
    {
        $event = $context->requireWritable($request->user());
        abort_unless((int) $shift->event_id === (int) $event->id, 404);

        return Inertia::render('Team/CopyShift', [
            'event' => [...$event->only('id', 'name'), 'is_locked' => $event->isLocked()],
            'canManage' => true,
            'locations' => $locations->optionsFor($event),
            'roles' => ShiftSlotReferences::options(),
            'labelColors' => LabelColors::ALL,
            'breakOptions' => ShiftBreaks::options(),
            'canConfigureMeals' => Gate::allows('meals.edit'),
            'prefill' => (new ShiftCopyResource($service->copyDraft($shift)))->resolve($request),
            'returnContext' => ShiftReturnContext::from($request->validated()),
        ]);
    }
}
