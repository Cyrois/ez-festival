<?php

namespace App\Http\Requests\Kitchen;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMealTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('meals.view');
    }

    public function rules(): array
    {
        return [
            'draw' => ['required', 'integer', 'min:0'],
            'start' => ['required', 'integer', 'min:0'],
            'length' => ['required', 'integer', 'between:1,100'],
            'search.value' => ['nullable', 'string', 'max:255'],
            'order' => ['sometimes', 'array', 'max:1'],
            'order.0.column' => ['sometimes', 'integer', 'in:0,1'],
            'order.0.dir' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
