<?php

namespace App\Http\Controllers\Setup\Concerns;

use App\Support\OrganizationContext;
use App\Support\Timezones;

trait InteractsWithSetup
{
    protected function organization(): OrganizationContext
    {
        return app(OrganizationContext::class);
    }

    /**
     * @return list<string>
     */
    protected function timezones(): array
    {
        return Timezones::common();
    }
}
