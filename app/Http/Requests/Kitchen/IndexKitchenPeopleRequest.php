<?php

namespace App\Http\Requests\Kitchen;

use Illuminate\Foundation\Http\FormRequest;

class IndexKitchenPeopleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('meals.view');
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'page' => ['sometimes', 'integer', 'min:1']];
    }
}
