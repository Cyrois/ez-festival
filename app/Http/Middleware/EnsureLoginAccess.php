<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if ($user !== null && ! $user->canSignIn()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()
                ->route('login')
                ->withErrors(['access' => __('auth.no_event_access')]);
        }

        return $next($request);
    }
}
