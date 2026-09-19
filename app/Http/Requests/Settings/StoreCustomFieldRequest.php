<?php

namespace App\Http\Requests\Settings;

use App\Models\CustomField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target' => ['required', 'string', Rule::in(CustomField::WIRED_TARGETS)],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(CustomField::TYPES)],
            'required' => ['boolean'],
            'options' => ['nullable', 'array', 'max:100'],
            'options.*' => ['required', 'string', 'max:255', 'distinct'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $options = $this->input('options');

            if ($this->input('type') === 'select' && (! is_array($options) || count($options) === 0)) {
                $validator->errors()->add('options', __('settings.custom_fields.validation.options_required'));
            }
        });
    }
}
