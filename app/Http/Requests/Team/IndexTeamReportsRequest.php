<?php

namespace App\Http\Requests\Team;

use App\Support\EventContext;
use Illuminate\Validation\Rule;

class IndexTeamReportsRequest extends IndexTeamHoursReportRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'view' => ['sometimes', Rule::in(['summary', 'team', 'shift', 'location'])],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('event_id', app(EventContext::class)->current($this->user())?->id)],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->where('event_id', app(EventContext::class)->current($this->user())?->id)],
        ];
    }
}
