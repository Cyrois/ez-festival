<?php

namespace App\Http\Controllers;

use App\Http\Requests\LockEventRequest;
use App\Http\Requests\UnlockEventRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->primaryOrganization() ?? $user->ensureOrganization();

        $events = $organization->events()
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Event $event) => $this->eventPayload($event, $organization->active_event_id));

        return Inertia::render('Events/Index', [
            'events' => $events,
        ]);
    }

    public function show(Request $request, Event $event): Response
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->primaryOrganization() ?? $user->ensureOrganization();

        abort_unless((int) $event->organization_id === (int) $organization->id, 404);

        return Inertia::render('Events/Show', [
            'event' => $this->eventPayload($event, $organization->active_event_id),
        ]);
    }

    public function lock(LockEventRequest $request, Event $event): RedirectResponse
    {
        $event->lock();

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Event locked.');
    }

    public function unlock(UnlockEventRequest $request, Event $event): RedirectResponse
    {
        $event->unlock();

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Event unlocked.');
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Event $event, ?int $activeEventId): array
    {
        $isActive = $activeEventId !== null && (int) $activeEventId === (int) $event->id;
        $isLocked = $event->isLocked();

        return [
            'id' => $event->id,
            'name' => $event->name,
            'starts_on' => $event->starts_on->toDateString(),
            'ends_on' => $event->ends_on->toDateString(),
            'timezone' => $event->timezone,
            'is_locked' => $isLocked,
            'is_active' => $isActive,
            'is_past' => $event->isPast(),
            'is_read_only' => ! $isActive || $isLocked,
        ];
    }
}
