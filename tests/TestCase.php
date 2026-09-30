<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function grantAdminAccess(User $user): User
    {
        $user->forceFill(['is_admin' => true])->save();
        $user->person()->update(['can_log_in' => true]);

        return $user->fresh();
    }
}
