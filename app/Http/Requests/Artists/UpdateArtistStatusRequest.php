<?php

namespace App\Http\Requests\Artists;

use App\Models\ArtistEngagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArtistStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ArtistEngagement::STATUSES)],
        ];
    }
}
