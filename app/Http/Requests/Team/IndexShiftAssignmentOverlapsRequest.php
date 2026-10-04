<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\ShiftAssignmentRules;
use Illuminate\Foundation\Http\FormRequest;

class IndexShiftAssignmentOverlapsRequest extends FormRequest
{
    use ShiftAssignmentRules;

    public function authorize(): bool
    {
        return $this->authorizeShift();
    }

    public function rules(): array
    {
        return $this->hoursRules();
    }
}
