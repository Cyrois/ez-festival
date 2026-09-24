<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
        Gate::define('manage-shift-templates', fn (User $user): bool => $user !== null);
        Gate::define('manage-team', fn (User $user): bool => $user !== null);
    }
}
