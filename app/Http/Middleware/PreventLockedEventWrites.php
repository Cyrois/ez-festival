<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block non-safe HTTP methods when the org active event is locked.
 * Lock/unlock of events are registered outside this middleware.
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

        $organization = $user->primaryOrganization();
        $event = $organization?->activeEvent;

        if ($event !== null && $event->isLocked()) {
            abort(403, 'This event is locked and read-only.');
        }

        return $next($request);
    }
}
