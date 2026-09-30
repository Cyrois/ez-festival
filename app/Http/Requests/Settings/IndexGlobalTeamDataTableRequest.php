<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexGlobalTeamDataTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-global-team');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'draw' => ['required', 'integer', 'min:0'],
            'start' => ['required', 'integer', 'min:0'],
            'length' => ['required', 'integer', 'between:1,100'],
            'search' => ['sometimes', 'array'],
            'search.value' => ['nullable', 'string', 'max:255'],
            'order' => ['sometimes', 'array', 'max:1'],
            'order.0.column' => ['sometimes', 'integer', 'in:0,1'],
            'order.0.dir' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
