<?php

namespace App\Http\Requests\Team;

use App\Rules\EventLocalTimeRule;
use App\Support\EventContext;
use App\Support\ShiftReturnContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('scheduling.edit');
    }

    public function rules(EventContext $eventContext): array
    {
        $event = $eventContext->requireCurrent($this->user());

        return [
            'location_id' => ['sometimes', 'integer', Rule::exists('locations', 'id')->where('event_id', $event->id)],
            'starts_at' => ['required_with:ends_at', 'date_format:Y-m-d\TH:i', new EventLocalTimeRule($event->timezone)],
            'ends_at' => ['required_with:starts_at', 'date_format:Y-m-d\TH:i', new EventLocalTimeRule($event->timezone), 'after:starts_at'],
            ...ShiftReturnContext::rules(),
        ];
    }
}
