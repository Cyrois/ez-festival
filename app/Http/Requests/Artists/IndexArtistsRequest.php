<?php

namespace App\Http\Requests\Artists;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexArtistsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'labels' => ['sometimes', 'array', 'max:50'],
            'labels.*' => ['integer', 'distinct', Rule::exists('artist_labels', 'id')],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
