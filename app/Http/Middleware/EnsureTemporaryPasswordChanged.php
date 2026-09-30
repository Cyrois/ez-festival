<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTemporaryPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password
            && ! $request->routeIs('password.temporary.*')
            && ! $request->routeIs('logout')) {
            return redirect()->route('password.temporary.edit');
        }

        return $next($request);
    }
}
