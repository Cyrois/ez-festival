<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Support\Collection;

trait TeamHoursReportValues
{
    private function hours(array|object $row): array
    {
        $row = (array) $row;
        $volunteer = ($row['employment_type'] ?? null) === 'volunteer';

        return [
            'scheduled_minutes' => (int) $row['scheduled_minutes'],
            'approved_minutes' => null,
            'unapproved_minutes' => null,
            'scheduled_cost_cents' => $volunteer ? null : (int) $row['scheduled_cost_cents'],
            'approved_cost_cents' => null,
            'missing_rate_count' => (int) $row['missing_rate_count'],
        ];
    }

    private function hourTotals(Collection $rows): array
    {
        return $this->hours([
            'scheduled_minutes' => $rows->sum('scheduled_minutes'),
            'scheduled_cost_cents' => $rows->sum('scheduled_cost_cents'),
            'missing_rate_count' => $rows->sum('missing_rate_count'),
        ]);
    }

    private function hourlyPay(object $person): ?string
    {
        return $person->employment_type === 'paid' && $person->hourly_pay !== null
            ? number_format((float) $person->hourly_pay, 2, '.', '')
            : null;
    }
}
