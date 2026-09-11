<?php

namespace App\Http\Controllers\Setup\Concerns;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

trait InteractsWithSetup
{
    protected function organization(Request $request): Organization
    {
        /** @var User $user */
        $user = $request->user();

        return $user->primaryOrganization() ?? $user->ensureOrganization();
    }

    /**
     * @return list<string>
     */
    protected function timezones(): array
    {
        return [
            'America/Vancouver',
            'America/Edmonton',
            'America/Winnipeg',
            'America/Toronto',
            'America/Halifax',
            'America/St_Johns',
            'America/New_York',
            'America/Chicago',
            'America/Denver',
            'America/Los_Angeles',
            'UTC',
        ];
    }
}
