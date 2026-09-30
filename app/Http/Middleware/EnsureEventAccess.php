<?php

namespace App\Http\Middleware;

use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\PassAssignment;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEventAccess
{
    /**
     * Enforce event visibility for both current-context pages and routes that
     * bind an event-scoped record directly.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $events = collect($request->route()?->parameters() ?? [])
            ->map(fn (mixed $parameter): ?Event => $this->eventFor($parameter))
            ->filter()
            ->unique(fn (Event $event): int => $event->getKey())
            ->values();

        abort_if($events->count() > 1, 404);

        $event = $events->first();

        if ($event !== null) {
            abort_unless($user->canAccessEvent($event), 404);
        }

        return $next($request);
    }

    private function eventFor(mixed $parameter): ?Event
    {
        if ($parameter instanceof Event) {
            return $parameter;
        }

        if ($parameter instanceof ExpectedEntitlement) {
            return $parameter->entitlementItem?->event;
        }

        if ($parameter instanceof PassAssignment) {
            return $parameter->passType?->event;
        }

        if ($parameter instanceof EntitlementItem) {
            return $parameter->event;
        }

        if (! $parameter instanceof Model) {
            return null;
        }

        $eventId = $parameter->getAttribute('event_id');

        return $eventId === null ? null : Event::query()->find($eventId);
    }
}
