<?php

namespace App\Http\Requests\Team;

use App\Models\TeamForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexTeamFormsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view-team');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(TeamForm::STATUSES)],
        ];
    }
}
