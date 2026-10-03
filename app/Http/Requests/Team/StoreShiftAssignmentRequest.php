<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\ShiftAssignmentRules;
use App\Support\ShiftReturnContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftAssignmentRequest extends FormRequest
{
    use ShiftAssignmentRules;

    public function authorize(): bool
    {
        return $this->authorizeShift();
    }

    public function rules(): array
    {
        return [...$this->assignmentRules(), ...ShiftReturnContext::rules(),
            'team_engagement_id' => ['required', 'integer', Rule::exists('team_engagements', 'id')
                ->where('event_id', $this->route('shift')->event_id)->where('status', 'hired')],
        ];
    }
}
