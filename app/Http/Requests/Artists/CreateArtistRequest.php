<?php

namespace App\Http\Requests\Artists;

use Illuminate\Foundation\Http\FormRequest;

class CreateArtistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('artists.edit') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
