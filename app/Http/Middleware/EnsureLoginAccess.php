<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoginAccess
{
    /**
     * Refuse an authenticated account as soon as its login switch, admin flag,
     * or active role assignments no longer allow back-office access.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user !== null && ! $user->canSignIn(), 403);

        return $next($request);
    }
}
