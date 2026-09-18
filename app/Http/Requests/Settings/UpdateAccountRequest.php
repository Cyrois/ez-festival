<?php

namespace App\Http\Requests\Settings;

use App\Models\CustomField;
use App\Support\OrganizationContext;
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
                $validator->errors()->add('custom_fields', 'One or more custom fields are unavailable.');
            }
        });

        $rules = [];

        foreach ($fields as $field) {
            $rules["custom_fields.{$field->id}"] = $this->valueRules($field);
        }

        $validator->addRules($rules);
    }

    /**
     * @return Collection<int, CustomField>
     */
    public function customFields()
    {
        return CustomField::query()
            ->where('organization_id', app(OrganizationContext::class)->organization()->id)
            ->forTarget(CustomField::TARGET_USER)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array<int, mixed>
     */
    private function valueRules(CustomField $field): array
    {
        $presence = $field->required ? ['required'] : ['nullable'];

        return match ($field->type) {
            'textarea' => [...$presence, 'string', 'max:5000'],
            'number' => [...$presence, 'numeric'],
            'date' => [...$presence, 'date'],
            'select' => [...$presence, 'string', Rule::in($field->options ?? [])],
            'checkbox' => ['required', 'boolean'],
            default => [...$presence, 'string', 'max:255'],
        };
    }
}
