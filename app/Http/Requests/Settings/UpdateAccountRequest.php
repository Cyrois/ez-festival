<?php

namespace App\Http\Requests\Settings;

use App\Models\CustomField;
use App\Support\CustomFieldValueRules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $fields = $this->customFields();

        $validator->after(function ($validator) use ($fields): void {
            $providedIds = array_map('intval', array_keys($this->input('custom_fields', [])));
            $knownIds = $fields->pluck('id')->all();

            if (array_diff($providedIds, $knownIds) !== []) {
                $validator->errors()->add('custom_fields', __('validation.custom_fields.unavailable'));
            }
        });

        $rules = [];

        foreach ($fields as $field) {
            $rules["custom_fields.{$field->id}"] = CustomFieldValueRules::for($field);
        }

        $validator->addRules($rules);
    }

    /**
     * @return Collection<int, CustomField>
     */
    public function customFields()
    {
        return CustomField::query()
            ->forTarget(CustomField::TARGET_USER)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
