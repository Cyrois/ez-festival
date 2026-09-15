<?php

namespace App\Support;

class Timezones
{
    /**
     * Curated common timezones offered in Setup and Settings.
     *
     * @return list<string>
     */
    public static function common(): array
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
