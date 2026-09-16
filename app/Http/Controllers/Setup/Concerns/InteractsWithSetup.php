<?php

namespace App\Http\Controllers\Setup\Concerns;

use App\Support\ClientContext;
use App\Support\Timezones;

trait InteractsWithSetup
{
    protected function client(): ClientContext
    {
        return app(ClientContext::class);
    }

    /**
     * @return list<string>
     */
    protected function timezones(): array
    {
        return Timezones::common();
    }
}
