<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateFeatureFlagRequest;
use App\Services\FeatureFlagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FeatureFlagController extends Controller
{
    public function __construct(private readonly FeatureFlagService $featureFlags) {}

    public function index(): Response
    {
        Gate::authorize('manage-feature-flags');

        $values = $this->featureFlags->all();

        return Inertia::render('Settings/FeatureFlags', [
            'flags' => collect($this->featureFlags->definitions())
                ->map(fn (array $definition, string $key): array => [
                    'key' => $key,
                    'enabled' => $values[$key],
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                ])
                ->values(),
        ]);
    }

    public function update(UpdateFeatureFlagRequest $request, string $flag): RedirectResponse
    {
        abort_unless($this->featureFlags->exists($flag), 404);

        $this->featureFlags->set(
            $flag,
            $request->boolean('enabled'),
            $request->user(),
        );

        return back()->with('success', __('settings.feature_flags.toast.updated'));
    }
}
