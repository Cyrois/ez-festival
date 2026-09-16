<?php

namespace App\Http\Controllers;

use App\Http\Requests\LockEventRequest;
use App\Http\Requests\UnlockEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('settings.events.index');
    }

    public function show(Request $request, Event $event): Response
    {
        /** @var User $user */
        $user = $request->user();
        $activeEventId = $user->effectiveEvent()?->id;

        return Inertia::render('Events/Show', [
            'event' => EventResource::toArray($event, $activeEventId),
        ]);
    }

    public function lock(LockEventRequest $request, Event $event): RedirectResponse
    {
        $event->lock();

        return redirect()
            ->route('settings.events.index')
            ->with('success', __('events.toast.locked'))
            ->with('success_title', __('events.toast.locked_title'));
    }

    public function unlock(UnlockEventRequest $request, Event $event): RedirectResponse
    {
        $event->unlock();

        return redirect()
            ->route('settings.events.index')
            ->with('success', __('events.toast.unlocked'))
            ->with('success_title', __('events.toast.unlocked_title'));
    }
}
