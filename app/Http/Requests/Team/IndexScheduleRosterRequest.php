<?php

namespace App\Http\Requests\Team;

use App\Support\EventContext;

class IndexScheduleRosterRequest extends IndexScheduleGridRequest
{
    public function rules(EventContext $eventContext): array
    {
        $rules = parent::rules($eventContext);
        $rules['location_id'][0] = 'required';

        return $rules;
    }
}
