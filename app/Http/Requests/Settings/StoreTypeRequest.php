<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'types' => ['sometimes', 'array'],
            'types.*.id' => ['required', 'integer'],
            'types.*.position' => ['required', 'integer', 'min:0'],
        ];
    }
}
