<?php

namespace App\Http\Requests\Credentials;

use App\Support\LabelColors;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreEntitlementItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('credentials.entitlements.edit')
            && ((int) $this->input('opening_balance', 0) <= 0 || Gate::allows('credentials.stock.adjust'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }

        if (! $this->filled('location_id')) {
            $this->merge(['location_id' => null]);
        }

        if (! $this->has('opening_balance')) {
            $this->merge(['opening_balance' => 0]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'low_stock_level' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:2147483647'],
            'opening_balance' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'location_id' => [
                'nullable',
                Rule::requiredIf(fn (): bool => (int) $this->input('opening_balance', 0) > 0),
                'integer',
                Rule::exists('locations', 'id')->where('event_id', $this->route('event')->id),
            ],
            'label_ids' => ['sometimes', 'array', 'max:50'],
            'label_ids.*' => ['integer', 'distinct', Rule::exists('entitlement_item_labels', 'id')->where('event_id', $this->route('event')->id)],
            'new_labels' => ['sometimes', 'array', 'max:20'],
            'new_labels.*' => ['array:name,color'],
            'new_labels.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'new_labels.*.color' => ['required', Rule::in(LabelColors::ALL)],
        ];
    }

    public function messages(): array
    {
        return ['location_id.required' => __('credentials.entitlements.errors.location_required')];
    }
}
