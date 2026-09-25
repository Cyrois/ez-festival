<?php

namespace App\Http\Requests\Team;

use App\Models\TeamEngagement;
use App\Models\TeamForm;
use App\Models\TeamFormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicTeamFormRequest extends FormRequest
{
    private ?TeamForm $resolvedForm = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->teamForm()->fields as $field) {
            $presence = $field->required ? 'required' : 'nullable';

            if ($field->key === TeamFormField::KEY_NAME) {
                $rules['name'] = ['required', 'string', 'max:255'];
            } elseif ($field->key === TeamFormField::KEY_EMAIL) {
                $rules['email'] = ['required', 'email', 'max:255'];
            } elseif ($field->key === TeamFormField::KEY_PHONE) {
                $rules['phone'] = [$presence, 'string', 'max:50'];
            } elseif ($field->key === TeamFormField::KEY_EMPLOYMENT_TYPE) {
                $rules['employment_type'] = [$presence, Rule::in(TeamEngagement::EMPLOYMENT_TYPES)];
            } elseif ($field->customField !== null) {
                $rules["custom_fields.{$field->custom_field_id}"] = [
                    $presence,
                    ...$this->customFieldRules($field->customField->type, $field->customField->options ?? []),
                ];
            }
        }

        return $rules;
    }

    public function teamForm(): TeamForm
    {
        return $this->resolvedForm ??= TeamForm::query()
            ->with('fields.customField')
            ->where('slug', $this->route('slug'))
            ->where('status', 'live')
            ->firstOrFail();
    }

    /** @param array<int, string> $options */
    private function customFieldRules(string $type, array $options): array
    {
        return match ($type) {
            'select' => [Rule::in($options)],
            'textarea' => ['string', 'max:10000'],
            default => ['string', 'max:255'],
        };
    }
}
