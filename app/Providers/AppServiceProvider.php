<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\User;
use App\Services\EventAccessService;
use App\Support\OrganizationContext;
use App\Support\PermissionEvent;
use App\Support\Permissions;
use Illuminate\Http\Request;
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
        $this->app->scoped(EventAccessService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('team.member.update', fn (User $user): bool => Gate::forUser($user)->any(['team.edit', 'team.notes.add', 'team.change_role']));
        Gate::define('pass-assignment.edit', function (User $user): bool {
            $assignment = request()->route('assignment');
            $area = match (true) {
                $assignment->artist_engagement_id !== null => 'artists',
                $assignment->vendor_engagement_id !== null => 'vendors',
                $assignment->team_engagement_id !== null => 'team',
                default => 'patrons',
            };

            return Gate::forUser($user)->allows($area.'.edit', $assignment->passType->event);
        });
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);
        foreach (Permissions::keys() as $permission) {
            Gate::define($permission, function (User $user, mixed $record = null) use ($permission): bool {
                $event = $record instanceof Event ? $record : $record?->event;
                $event ??= app(PermissionEvent::class)->resolve(app(Request::class));

                return app(EventAccessService::class)->allows($user, $permission, $event);
            });
        }
        foreach (['manage-global-team', 'manage-roles', 'manage-feature-flags', 'view-credentials', 'manage-credentials', 'admin'] as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->isAdmin());
        }
    }
}
