<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
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
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'city' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }
}
