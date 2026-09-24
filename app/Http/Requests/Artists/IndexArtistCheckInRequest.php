<?php

namespace App\Http\Requests\Artists;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexArtistCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view-artists');
    }

    public function rules(): array
    {
        $eventId = $this->user()?->effectiveEvent()?->id ?? 0;

        return [
            'type' => ['sometimes', Rule::in(['all', 'artist', 'vendor', 'patron', 'team'])],
            'pass' => [
                'nullable',
                'integer',
                Rule::exists('pass_types', 'id')->where('event_id', $eventId),
            ],
            'status' => ['sometimes', Rule::in(['all', 'not_started', 'partial', 'complete'])],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
