<?php

namespace App\Http\Middleware;

use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Location;
use App\Models\User;
use App\Models\VendorEngagement;
use App\Support\EventContext;
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
    public function __construct(private readonly EventContext $eventContext) {}

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

        $primary = $this->eventContext->current($user);
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

        if ($engagement instanceof ArtistEngagement || $engagement instanceof VendorEngagement) {
            return $engagement->event;
        }

        $location = $request->route('location');

        if ($location instanceof Location) {
            return $location->event;
        }

        return $activeEvent;
    }
}
