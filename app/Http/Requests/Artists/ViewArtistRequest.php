<?php

namespace App\Http\Requests\Artists;

use Illuminate\Foundation\Http\FormRequest;

class ViewArtistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('artists.view') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
