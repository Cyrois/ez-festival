<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\ShiftAssignmentRules;
use App\Support\ShiftReturnContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftAssignmentRequest extends FormRequest
{
    use ShiftAssignmentRules;

    public function authorize(): bool
    {
        return $this->authorizeShift();
    }

    public function rules(): array
    {
        return [...$this->hoursRules(), ...ShiftReturnContext::rules()];
    }
}
