<?php

namespace App\Http\Requests\Kitchen;

use Illuminate\Foundation\Http\FormRequest;

class IndexMealsRequest extends FormRequest
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
        ];
    }
}
