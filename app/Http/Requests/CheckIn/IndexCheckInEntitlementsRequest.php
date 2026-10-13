<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexCheckInEntitlementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('checkin.view');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['artist', 'vendor', 'team'])],
            'engagement_id' => ['required', 'integer', 'min:1'],
            'person_id' => ['required', 'integer', 'min:1'],
            'draw' => ['required', 'integer', 'min:0'],
            'start' => ['required', 'integer', 'min:0'],
            'length' => ['required', 'integer', Rule::in([-1, ...range(1, 100)])],
            'search' => ['sometimes', 'array'],
            'search.value' => ['sometimes', 'nullable', 'string', 'max:255'],
            'order' => ['sometimes', 'array', 'max:1'],
            'order.0' => ['sometimes', 'array'],
            'order.0.column' => ['sometimes', 'integer', 'between:0,4'],
            'order.0.dir' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
