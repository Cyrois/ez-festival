<?php

namespace App\Support;

use App\Models\TeamEngagement;

class TeamHoursReportFilter
{
    public static function values(): array
    {
        return ['all', ...array_reverse(TeamEngagement::EMPLOYMENT_TYPES)];
    }

    public static function options(): array
    {
        return array_map(fn (string $value): array => ['value' => $value, 'label' => __('team.reports.filter.'.$value)], self::values());
    }
}
