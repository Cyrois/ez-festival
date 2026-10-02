<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConsumeEntitlementRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => filled($this->code) ? trim((string) $this->code) : null]);
    }

    public function authorize(): bool
    {
        return Gate::allows('checkin.edit');
    }

    public function rules(): array
    {
        $eventId = $this->user()?->effectiveEvent()?->id;

        return [
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('event_id', $eventId),
            ],
            'code' => ['nullable', 'string', 'max:255'],
        ];
    }
}
