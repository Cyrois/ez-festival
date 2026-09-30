<?php

namespace App\Providers;

use App\Models\TeamEngagement;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $databaseConnections = $this->app->environment('testing')
            ? ['pgsql', 'sqlite']
            : ['pgsql'];

        config()->set(
            'database.connections',
            Arr::only(config('database.connections'), $databaseConnections),
        );

        $this->app->scoped(OrganizationContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-feature-flags', fn (User $user): bool => $user !== null);
        Gate::define('view-artists', fn (User $user): bool => $user !== null);
        Gate::define('manage-artists', fn (User $user): bool => $user !== null);
        Gate::define('view-credentials', fn (User $user): bool => $user !== null);
        Gate::define('manage-credentials', fn (User $user): bool => $user !== null);
        Gate::define('view-team', fn (User $user): bool => $user !== null);
        Gate::define('manage-team', fn (User $user): bool => $user !== null);
        Gate::define('manage-global-team', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-roles', fn (User $user): bool => $user->isAdmin());
        Gate::define('can-read-team-notes', function (User $user, TeamEngagement $engagement): bool {
            if ($user->isAdmin()) {
                return true;
            }

            return TeamEngagement::query()
                ->where('event_id', $engagement->event_id)
                ->where('person_id', $user->person_id)
                ->whereHas(
                    'role',
                    fn ($query) => $query
                        ->where('active', true)
                        ->where('can_read_team_notes', true),
                )
                ->exists();
        });
    }
}
