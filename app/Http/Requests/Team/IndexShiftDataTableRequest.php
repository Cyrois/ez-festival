<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexShiftDataTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('team.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'draw' => ['sometimes', 'integer', 'min:0'],
            'start' => ['sometimes', 'integer', 'min:0'],
            'length' => ['sometimes', 'integer', Rule::in([10, 25, 50])],
            'search' => ['sometimes', 'array'],
            'search.value' => ['sometimes', 'nullable', 'string', 'max:255'],
            'order' => ['sometimes', 'array', 'max:1'],
            'order.0' => ['sometimes', 'array'],
            'order.0.column' => ['sometimes', 'integer', 'between:0,3'],
            'order.0.dir' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
