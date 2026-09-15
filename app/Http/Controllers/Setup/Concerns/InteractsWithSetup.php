<?php

namespace App\Http\Controllers\Setup\Concerns;

use App\Models\Organization;
use App\Models\User;
use App\Support\Timezones;
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
        return Timezones::common();
    }
}
