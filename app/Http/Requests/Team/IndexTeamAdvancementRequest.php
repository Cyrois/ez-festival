<?php

namespace App\Http\Requests\Team;

use App\Models\TeamEngagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexTeamAdvancementRequest extends FormRequest
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
            'employment_types' => ['sometimes', 'array', 'max:2'],
            'employment_types.*' => ['string', 'distinct', Rule::in(TeamEngagement::EMPLOYMENT_TYPES)],
            'view' => ['sometimes', Rule::in(['columns', 'list'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
