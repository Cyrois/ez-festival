<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $organization = $user?->primaryOrganization();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user
                    ? $user->only('id', 'name', 'email')
                    : null,
            ],
            'organization' => $organization
                ? [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'setup_completed' => $organization->setupIsComplete(),
                ]
                : null,
            'activeEvent' => $organization?->activeEvent
                ? [
                    'id' => $organization->activeEvent->id,
                    'name' => $organization->activeEvent->name,
                ]
                : null,
        ];
    }
}
