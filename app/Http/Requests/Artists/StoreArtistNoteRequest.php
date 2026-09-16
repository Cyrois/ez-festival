<?php

namespace App\Http\Requests\Artists;

use Illuminate\Foundation\Http\FormRequest;

class StoreArtistNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('body')) {
            $this->merge(['body' => trim((string) $this->input('body'))]);
        }
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
