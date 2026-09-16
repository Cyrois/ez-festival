<?php

namespace App\Http\Middleware;

use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Location;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block non-safe HTTP methods against a locked event.
 *
 * Resolves the target event from route {event}, {engagement}->event,
 * {location}->event, or the user's primary event for active-context routes.
 * Lock/unlock routes must not use this middleware.
 */
class PreventLockedEventWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $primary = $user->effectiveEvent();
        $event = $this->resolveEvent($request, $primary);

        if ($event === null) {
            return $next($request);
        }

        $event->ensureWritable();

        return $next($request);
    }

    private function resolveEvent(Request $request, ?Event $activeEvent): ?Event
    {
        $routeEvent = $request->route('event');

        if ($routeEvent instanceof Event) {
            return $routeEvent;
        }

        $engagement = $request->route('engagement');

        if ($engagement instanceof ArtistEngagement) {
            return $engagement->event;
        }

        $location = $request->route('location');

        if ($location instanceof Location) {
            return $location->event;
        }

        return $activeEvent;
    }
}
