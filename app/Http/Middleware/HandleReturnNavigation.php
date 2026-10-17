<?php

namespace App\Http\Middleware;

use App\Http\Requests\Navigation\ReturnNavigationRequest;
use App\Support\ReturnNavigation;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleReturnNavigation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->has('return_to')) {
            return $next($request);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            $destination = $request->input('return_to');
            if (! is_string($destination) || ! ReturnNavigation::valid($destination)) {
                return $next($request);
            }
        }

        $data = app(ReturnNavigationRequest::class)->validated();
        $link = ReturnNavigation::link($data['return_to'], $request->user());
        $request->attributes->set('return_navigation', $link);
        $response = $next($request);

        if ($link !== null && $response instanceof RedirectResponse) {
            $target = $response->getTargetUrl();
            $parts = parse_url($target);
            // Only carry context through this application's existing detail redirects.
            if (isset($parts['host']) && $parts['host'] !== $request->getHost()) {
                return $response;
            }
            $path = $parts['path'] ?? '';
            if (ReturnNavigation::detailLanding($path) !== null) {
                parse_str($parts['query'] ?? '', $query);
                $query['return_to'] = $link['href'];
                $response->setTargetUrl(url($path).'?'.http_build_query($query));
            } elseif ($path === parse_url($link['href'], PHP_URL_PATH)) {
                $response->setTargetUrl(url($link['href']));
            }
        }

        return $response;
    }
}
