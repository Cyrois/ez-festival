<?php

namespace App\Http\Middleware;

use App\Support\ClientContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    public function __construct(private readonly ClientContext $client) {}

    /**
     * Redirect users with incomplete setup away from the main app.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $this->client->setupIsComplete()) {
            return redirect()->route('setup.event');
        }

        return $next($request);
    }
}
