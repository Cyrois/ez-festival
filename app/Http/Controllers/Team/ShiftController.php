<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\CreateShiftRequest;
use App\Http\Requests\Team\DestroyShiftRequest;
use App\Http\Requests\Team\StoreShiftRequest;
use App\Http\Requests\Team\UpdateShiftRequest;
use App\Models\Event;
use App\Models\Shift;
use App\Support\EventContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function create(CreateShiftRequest $request, EventContext $eventContext): Response
    {
        $event = $eventContext->requireWritable($request->user());

        return Inertia::render('Team/CreateShift', [
            'event' => $event->only('id', 'name'),
            'locations' => $event->locations()->orderBy('name')->get(['id', 'name']),
        ]);
    }

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
            'shift' => [
                'id' => $shift->id,
                'name' => $shift->name,
                'location_id' => $shift->location_id,
                'starts_at' => $shift->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $shift->ends_at->format('Y-m-d\TH:i'),
            ],
            'locations' => $event->locations()->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('manage-team'),
        ]);
    }

    public function store(
        StoreShiftRequest $request,
        Event $event,
        EventContext $eventContext,
    ): RedirectResponse {
        $eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $shift = $event->shifts()->create($request->validated());

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

        $eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $shift->update($request->validated());

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

        $eventContext->requireCurrentEvent($request->user(), $event, writable: true);
        $shift->delete();

        return redirect()->route('team.scheduling', ['tab' => 'list'])
            ->with('success', __('team.scheduling.toast.deleted'))
            ->with('success_title', __('toast.saved_title'));
    }
}
