<?php

namespace Tests;

use App\Models\Event;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function grantRoleAccess(User $user, array $permissions = ['team.view', 'artists.view', 'checkin.view']): User
    {
        $user->forceFill(['is_admin' => false])->save();
        $user->person()->update(['can_log_in' => true]);
        $role = Role::query()->updateOrCreate(['name_key' => 'test access '.$user->id], ['name' => 'Test access '.$user->id, 'permissions' => $permissions]);
        foreach (Event::all() as $event) {
            TeamEngagement::query()->updateOrCreate(
                ['event_id' => $event->id, 'person_id' => $user->person_id],
                ['role_id' => $role->id, 'status' => 'hired', 'employment_type' => 'volunteer'],
            );
        }

        return $user->fresh();
    }

    protected function grantAdminAccess(User $user): User
    {
        $user->forceFill(['is_admin' => true])->save();
        $user->person()->update(['can_log_in' => true]);

        return $user->fresh();
    }
}
