<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ReorderTypeRequest extends FormRequest
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
            'types' => ['required', 'array'],
            'types.*.id' => ['required', 'integer'],
            'types.*.position' => ['required', 'integer', 'min:0'],
        ];
    }
}
