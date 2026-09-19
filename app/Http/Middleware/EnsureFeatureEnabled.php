<?php

namespace App\Http\Middleware;

use App\Services\FeatureFlagService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $featureFlags = app(FeatureFlagService::class);

        abort_unless($featureFlags->exists($feature) && $featureFlags->enabled($feature), 404);

        return $next($request);
    }
}
