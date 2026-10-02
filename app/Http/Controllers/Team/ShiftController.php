<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\DestroyShiftRequest;
use App\Http\Requests\Team\StoreShiftRequest;
use App\Http\Requests\Team\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Event;
use App\Models\Shift;
use App\Repositories\LocationRepository;
use App\Services\ShiftService;
use App\Support\EventContext;
use App\Support\ShiftSlotReferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shifts,
        private readonly LocationRepository $locations,
    ) {}

    public function show(Request $request, Shift $shift, EventContext $eventContext): Response
    {
        Gate::authorize('view-team');

        $event = $eventContext->requireCurrent($request->user());
        abort_unless((int) $shift->event_id === (int) $event->id, 404);

        return Inertia::render('Team/Shift', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'is_locked' => $event->isLocked(),
            ],
            'shift' => (new ShiftResource($shift->load(['location:id,name', 'roleSlots.role:id,name'])))->resolve($request),
            'locations' => $this->locations->optionsFor($event),
            'canManage' => Gate::allows('manage-team'),
            'roles' => Gate::allows('manage-team') && ! $event->isLocked() ? ShiftSlotReferences::options() : [],
        ]);
    }

    public function store(
        StoreShiftRequest $request,
        Event $event,
        EventContext $eventContext,
    ): RedirectResponse {
        $eventContext->requireCurrentEvent($request->user(), $event);
        $shift = $this->shifts->create($event, $request->validated());

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
        $this->shifts->update($shift, $request->validated());

        return redirect()->route('team.shifts.show', $shift)
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
        $this->shifts->delete($shift);

        return redirect()->route('team.scheduling', ['tab' => 'list'])
            ->with('success', __('team.scheduling.toast.deleted'))
            ->with('success_title', __('toast.saved_title'));
    }
}
