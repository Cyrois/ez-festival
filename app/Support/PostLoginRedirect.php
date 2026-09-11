<?php

namespace App\Support;

use App\Models\User;

class PostLoginRedirect
{
    /**
     * Resolve where an authenticated user should land after login.
     */
    public static function for(User $user): string
    {
        $organization = $user->ensureOrganization();

        if (! $organization->setupIsComplete()) {
            return route('setup.event', absolute: false);
        }

        return route('dashboard', absolute: false);
    }
}
