<?php

namespace App\Http\Requests\Vendors;

use App\Models\CustomField;
use App\Models\VendorEngagement;
use App\Support\CustomFieldValueRules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(VendorEngagement::STATUSES)],
            'vendor_type_id' => ['nullable', 'integer', Rule::exists('vendor_types', 'id')],
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
            ->forTarget(CustomField::TARGET_VENDOR)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
