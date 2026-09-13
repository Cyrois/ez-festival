<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class ContinueArtistTypesRequest extends FormRequest
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
            'suggestions' => ['sometimes', 'array'],
            'suggestions.*.name' => ['required', 'string', 'max:255'],
        ];
    }
}
