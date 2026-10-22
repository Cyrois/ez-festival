<?php

namespace App\Http\Requests\Team\Concerns;

use App\Support\TeamHoursReportFilter;
use Illuminate\Validation\Rule;

trait FiltersTeamHoursReport
{
    protected function employmentTypeRules(): array
    {
        return ['employment_type' => ['sometimes', Rule::in(TeamHoursReportFilter::values())]];
    }

    public function employmentType(): string
    {
        return $this->validated()['employment_type'] ?? 'all';
    }
}
