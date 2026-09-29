<?php

namespace App\Providers;

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
    }
}
