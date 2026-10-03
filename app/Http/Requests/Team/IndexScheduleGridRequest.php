<?php

namespace App\Http\Requests\Team;

use App\Support\EventContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexScheduleGridRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('scheduling.view');
    }

    public function rules(EventContext $eventContext): array
    {
        $event = $eventContext->requireCurrent($this->user());

        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'location_id' => ['sometimes', 'integer', Rule::exists('locations', 'id')->where('event_id', $event->id)],
        ];
    }
}
