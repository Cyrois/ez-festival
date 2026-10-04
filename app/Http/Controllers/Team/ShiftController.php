<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\CreateShiftRequest;
use App\Http\Requests\Team\DestroyShiftRequest;
use App\Http\Requests\Team\ShowShiftRequest;
use App\Http\Requests\Team\StoreShiftRequest;
use App\Http\Requests\Team\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Event;
use App\Models\Shift;
use App\Repositories\LocationRepository;
use App\Repositories\ShiftAssignmentRepository;
use App\Services\ShiftService;
use App\Support\EventContext;
use App\Support\LabelColors;
use App\Support\ShiftBreaks;
use App\Support\ShiftReturnContext;
use App\Support\ShiftSlotReferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shifts,
        private readonly LocationRepository $locations,
        private readonly ShiftAssignmentRepository $roster,
    ) {}

    public function create(CreateShiftRequest $request, EventContext $eventContext): Response
    {
        $event = $eventContext->requireWritable($request->user());
        $data = $request->validated();
        if (isset($data['location_id'])) {
            $data['location_id'] = (int) $data['location_id'];
        }

        return Inertia::render('Team/CreateShift', [
            'event' => $event->only('id', 'name', 'timezone'),
            'locations' => $this->locations->optionsFor($event),
            'roles' => ShiftSlotReferences::options(),
            'labelColors' => LabelColors::ALL,
            'breakOptions' => ShiftBreaks::options(),
            'prefill' => array_intersect_key($data, array_flip(['location_id', 'starts_at', 'ends_at'])),
            'returnContext' => ShiftReturnContext::from($data),
        ]);
    }

    public function show(ShowShiftRequest $request, Shift $shift, EventContext $eventContext): Response
    {
        $event = $eventContext->requireCurrent($request->user());
        abort_unless((int) $shift->event_id === (int) $event->id, 404);

        return Inertia::render('Team/Shift', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'timezone' => $event->timezone,
                'is_locked' => $event->isLocked(),
            ],
            'shift' => (new ShiftResource($this->roster->loadRoster($shift)))->resolve($request),
            'locations' => $this->locations->optionsFor($event),
            'canManage' => Gate::allows('scheduling.edit'),
            'roles' => Gate::allows('scheduling.edit') && ! $event->isLocked() ? ShiftSlotReferences::options() : [],
            'labelColors' => LabelColors::ALL,
            'breakOptions' => ShiftBreaks::options(),
            'returnContext' => ShiftReturnContext::from($request->validated()),
        ]);
    }

    public function store(
        StoreShiftRequest $request,
        Event $event,
        EventContext $eventContext,
    ): RedirectResponse {
        $eventContext->requireCurrentEvent($request->user(), $event);
        $data = $request->validated();
        $shift = $this->shifts->create($event, ShiftReturnContext::without($data));

        if (isset($data['return_tab'])) {
            return redirect()->route('team.scheduling', ShiftReturnContext::schedulingParameters($data, $shift->starts_at->format('Y-m-d')))
                ->with('success', __('team.scheduling.toast.created'))
                ->with('success_title', __('toast.saved_title'));
        }

        return redirect()->route('team.shifts.show', $shift)
            ->with('success', __('team.scheduling.toast.created'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function update(
        UpdateShiftRequest $request,
        Event $event,
        Shift $shift,
        EventContext $eventContext,
    ): RedirectResponse {
        abort_unless((int) $shift->event_id === (int) $event->id, 404);

        $eventContext->requireCurrentEvent($request->user(), $event);
        $data = $request->validated();
        $this->shifts->update($shift, ShiftReturnContext::without($data));

        return (isset($data['return_tab'])
            ? redirect()->route('team.scheduling', ShiftReturnContext::schedulingParameters($data, substr($data['starts_at'], 0, 10)))
            : redirect()->route('team.shifts.show', $shift))
            ->with('success', __('team.scheduling.toast.updated'))
            ->with('success_title', __('toast.saved_title'));
    }

    public function destroy(
        DestroyShiftRequest $request,
        Event $event,
        Shift $shift,
        EventContext $eventContext,
    ): RedirectResponse {
        abort_unless((int) $shift->event_id === (int) $event->id, 404);

        $eventContext->requireCurrentEvent($request->user(), $event);
        $this->shifts->delete($shift, (int) ($request->validated()['assignment_count'] ?? 0));

        return redirect()->route('team.scheduling', ShiftReturnContext::schedulingParameters($request->validated()))
            ->with('success', __('team.scheduling.toast.deleted'))
            ->with('success_title', __('toast.saved_title'));
    }
}
