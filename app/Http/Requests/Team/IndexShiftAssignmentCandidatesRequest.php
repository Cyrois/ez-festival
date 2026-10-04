<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\ShiftAssignmentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexShiftAssignmentCandidatesRequest extends FormRequest
{
    use ShiftAssignmentRules;

    public function authorize(): bool
    {
        return $this->authorizeShift() && ! $this->route('shift')->event->isLocked();
    }

    public function rules(): array
    {
        return [...$this->hoursRules(),
            'shift_role_slot_id' => ['required_without:role_id', 'prohibits:role_id', 'integer', Rule::exists('shift_role_slots', 'id')->where('shift_id', $this->route('shift')->id)],
            'role_id' => ['required_without:shift_role_slot_id', 'prohibits:shift_role_slot_id', 'integer', Rule::exists('roles', 'id')->where('active', true)],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ];
    }
}
