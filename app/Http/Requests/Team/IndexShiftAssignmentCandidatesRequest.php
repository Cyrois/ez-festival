<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\ShiftAssignmentRules;
use Illuminate\Foundation\Http\FormRequest;

class IndexShiftAssignmentCandidatesRequest extends FormRequest
{
    use ShiftAssignmentRules;

    public function authorize(): bool
    {
        return $this->authorizeShift() && ! $this->route('shift')->event->isLocked();
    }

    public function rules(): array
    {
        return [...$this->assignmentRules(),
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ];
    }
}
