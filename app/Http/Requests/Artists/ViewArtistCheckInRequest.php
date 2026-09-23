<?php

namespace App\Http\Requests\Artists;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ViewArtistCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view-artists');
    }

    public function rules(): array
    {
        return [];
    }
}
