<?php

namespace App\Http\Middleware;

use App\Support\ClientContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly ClientContext $client) {}

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
        $event = $user?->effectiveEvent();

        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'success_title' => fn () => $request->session()->get('success_title'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => $user
                    ? $user->only('id', 'name', 'email')
                    : null,
            ],
            'client' => $user
                ? [
                    'name' => $this->client->name(),
                    'setup_completed' => $this->client->setupIsComplete(),
                ]
                : null,
            'activeEvent' => $event
                ? [
                    'id' => $event->id,
                    'name' => $event->name,
                    'is_locked' => $event->isLocked(),
                ]
                : null,
        ];
    }
}
