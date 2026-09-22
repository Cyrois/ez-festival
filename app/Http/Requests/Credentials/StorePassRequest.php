<?php

namespace App\Http\Requests\Credentials;

use App\Models\CustomField;
use App\Models\PassTypeLabel;
use App\Support\CustomFieldValueRules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-credentials');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }

        if ($this->has('new_labels') && is_array($this->input('new_labels'))) {
            $this->merge([
                'new_labels' => array_map(function ($label) {
                    if (! is_array($label) || ! array_key_exists('name', $label)) {
                        return $label;
                    }

                    return [...$label, 'name' => trim((string) $label['name'])];
                }, $this->input('new_labels')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'max_assignments' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'label_ids' => ['sometimes', 'array', 'max:50'],
            'label_ids.*' => ['integer', 'distinct', Rule::exists('pass_type_labels', 'id')],
            'new_labels' => ['sometimes', 'array', 'max:20'],
            'new_labels.*' => ['array:name,color'],
            'new_labels.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'new_labels.*.color' => ['required', Rule::in(PassTypeLabel::COLORS)],
            'entitlement_item_ids' => ['sometimes', 'array', 'max:100'],
            'entitlement_item_ids.*' => [
                'integer',
                Rule::exists('entitlement_items', 'id')->where('event_id', $this->route('event')->id),
            ],
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
    public function customFields(): Collection
    {
        return CustomField::query()
            ->forTarget(CustomField::TARGET_PASS)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
