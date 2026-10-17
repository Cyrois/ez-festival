<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

final class ReturnNavigation
{
    private const LANDINGS = [
        '/artists/advancing' => ['nav.artists', 'artists.view', ['search', 'labels', 'view', 'page']],
        '/vendors/advancing' => ['vendors.title', 'vendors.view', ['search', 'view', 'page']],
        '/team/advancement' => ['nav.team.advancement', 'team.view', ['search', 'employment_types', 'view', 'page']],
        '/team/scheduling' => ['nav.team.scheduling', 'scheduling.view', ['tab', 'date', 'location_id', 'view']],
        '/team/forms' => ['nav.team.forms', 'forms.view', ['search', 'status', 'page']],
        '/credentials/passes' => ['credentials.passes.title', 'credentials.passes.edit', []],
        '/credentials/entitlements' => ['credentials.entitlements.title', 'view-entitlements', []],
        '/check-in' => ['check_in.title', 'checkin.view', ['type', 'pass', 'status', 'search']],
        '/meals/settings' => ['meals.settings.title', 'meals.view', []],
        '/settings/roles' => ['settings.roles.title', 'admin', ['search', 'status']],
        '/settings/team' => ['settings.team.title', 'admin', []],
        '/settings/events' => ['settings.events.title', 'admin', []],
        '/events' => ['events.title', null, []],
    ];

    private const DETAILS = [
        '#^/artists/(?:create|engagements/\d+)$#' => '/artists/advancing',
        '#^/vendors/(?:create|engagements/\d+)$#' => '/vendors/advancing',
        '#^/team/members/(?:create|\d+)$#' => '/team/advancement',
        '#^/team/shifts/(?:create|\d+(?:/copy)?)$#' => '/team/scheduling',
        '#^/team/forms/(?:create|\d+/(?:edit|preview))$#' => '/team/forms',
        '#^/credentials/passes/(?:create|\d+/edit)$#' => '/credentials/passes',
        '#^/credentials/entitlements/\d+$#' => '/credentials/entitlements',
        '#^/check-in/(?:artists|vendors|teams)/\d+$#' => '/check-in',
        '#^/meals/settings/meals/(?:create|\d+/edit)$#' => '/meals/settings',
        '#^/settings/roles/(?:create|\d+/edit)$#' => '/settings/roles',
        '#^/settings/team/(?:create|\d+)$#' => '/settings/team',
        '#^/settings/events/(?:create|\d+/(?:edit|locations))$#' => '/settings/events',
        '#^/settings/locations$#' => '/settings/events',
        '#^/events/\d+$#' => '/events',
    ];

    public static function valid(string $destination): bool
    {
        if (strlen($destination) > 4096 || ! str_starts_with($destination, '/') || str_contains($destination, '\\') || preg_match('/[\x00-\x20\x7f]/', $destination)) {
            return false;
        }

        $parts = parse_url($destination);
        if ($parts === false || array_diff(array_keys($parts), ['path', 'query']) !== [] || ! isset(self::LANDINGS[$parts['path'] ?? ''])) {
            return false;
        }

        parse_str($parts['query'] ?? '', $query);
        $allowed = [...self::LANDINGS[$parts['path']][2], 'restore'];
        foreach ($query as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                return false;
            }
            if (is_array($value)) {
                if (! in_array($key, ['labels', 'employment_types'], true) || count($value) > 50 || count(array_filter($value, 'is_string')) !== count($value)) {
                    return false;
                }
            } elseif (! is_string($value) || mb_strlen($value) > 255 || ($key === 'restore' && $value !== '1')) {
                return false;
            }
        }

        return true;
    }

    public static function detailLanding(string $path): ?string
    {
        foreach (self::DETAILS as $pattern => $landing) {
            if (preg_match($pattern, $path)) {
                return $landing;
            }
        }

        return null;
    }

    public static function link(string $destination, User $user): ?array
    {
        if (! self::valid($destination)) {
            return null;
        }
        $path = parse_url($destination, PHP_URL_PATH);
        [$label, $ability] = self::LANDINGS[$path];
        if ($ability !== null && ! $user->can($ability)) {
            return null;
        }

        return ['href' => $destination, 'page' => $label];
    }

    public static function forRequest(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }
        if ($return = $request->attributes->get('return_navigation')) {
            return $return;
        }

        $path = '/'.$request->path();
        if (isset(self::LANDINGS[$path])) {
            $link = self::link($request->getRequestUri(), $user);

            return $link === null ? null : [...$link, 'landing' => true];
        }

        $landing = self::detailLanding($path);
        if ($landing === '/team/scheduling') {
            $parameters = ShiftReturnContext::schedulingParameters($request->query());
            $landing .= '?'.http_build_query($parameters);
        }

        return $landing === null ? null : self::link($landing, $user);
    }
}
