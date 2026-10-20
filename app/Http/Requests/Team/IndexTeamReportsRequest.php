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
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->where('event_id', app(EventContext::class)->current($this->user())?->id)],
        ];
    }
}
