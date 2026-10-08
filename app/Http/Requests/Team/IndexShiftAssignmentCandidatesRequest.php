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
        return $this->authorizeShift() && ! ($this->route('shift')?->event ?? $this->route('event'))->isLocked();
    }

    public function rules(): array
    {
        return [...$this->hoursRules(),
            ...$this->proposedShiftRules(),
            'extra' => ['sometimes', 'boolean', 'accepted', 'prohibits:shift_role_slot_id,role_id,role_filter'],
            'shift_role_slot_id' => $this->route('shift')
                ? ['required_without_all:role_id,extra', 'prohibits:role_id,extra', 'integer', Rule::exists('shift_role_slots', 'id')->where('shift_id', $this->route('shift')->id)]
                : ['prohibited'],
            'role_id' => ['required_without_all:shift_role_slot_id,extra', 'prohibits:shift_role_slot_id,extra', 'integer', Rule::exists('roles', 'id')->where('active', true)],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'role_filter' => ['sometimes', Rule::in(['everyone', 'has_role'])],
            'selected_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
