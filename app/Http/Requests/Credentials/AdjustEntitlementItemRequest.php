<?php

namespace App\Http\Requests\Credentials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdjustEntitlementItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-credentials');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason')) {
            $this->merge(['reason' => trim((string) $this->input('reason'))]);
        }
    }

    public function rules(): array
    {
        return [
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('event_id', $this->route('event')->id),
            ],
            'direction' => ['required', Rule::in(['add', 'remove'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
