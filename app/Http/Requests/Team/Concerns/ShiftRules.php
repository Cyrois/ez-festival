<?php

namespace App\Http\Requests\Team\Concerns;

use App\Models\Event;
use App\Support\LabelColors;
use Illuminate\Validation\Rule;

trait ShiftRules
{
    /**
     * @return array<string, mixed>
     */
    protected function shiftRules(Event $event): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['sometimes', Rule::in(LabelColors::ALL)],
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('event_id', $event->id),
            ],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ];
    }
}
