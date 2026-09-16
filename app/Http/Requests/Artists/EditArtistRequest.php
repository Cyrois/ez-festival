<?php

namespace App\Http\Requests\Artists;

use Illuminate\Foundation\Http\FormRequest;

class EditArtistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
