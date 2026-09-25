<?php

namespace App\Http\Requests\Team;

use App\Models\TeamForm;
use App\Models\TeamFormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTeamFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-team');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->formRules();
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateBuiltins($validator)];
    }

    /** @return array<string, mixed> */
    protected function formRules(): array
    {
        $form = $this->route('teamForm');
        $uniqueSlug = Rule::unique('team_forms', 'slug');

        if ($form instanceof TeamForm) {
            $uniqueSlug->ignore($form->id);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                $uniqueSlug,
            ],
            'status' => ['required', Rule::in(TeamForm::STATUSES)],
            'fields' => ['required', 'array', 'min:3'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.key' => ['required', 'string', 'max:80', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(['text', 'textarea', 'email', 'phone', 'select'])],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.options' => ['nullable', 'array', 'max:50'],
            'fields.*.options.*' => ['required', 'string', 'max:255', 'distinct'],
        ];
    }

    protected function validateBuiltins(Validator $validator): void
    {
        $fields = collect($this->input('fields', []));

        foreach ([TeamFormField::KEY_NAME, TeamFormField::KEY_EMAIL, TeamFormField::KEY_PHONE] as $key) {
            if ($fields->where('key', $key)->count() !== 1) {
                $validator->errors()->add('fields', __('team.forms.errors.required_fields'));
            }
        }

        foreach ([TeamFormField::KEY_NAME, TeamFormField::KEY_EMAIL] as $key) {
            $field = $fields->firstWhere('key', $key);

            if (! ($field['required'] ?? false)) {
                $validator->errors()->add('fields', __('team.forms.errors.identity_fields_required'));
            }
        }

        foreach ($fields as $index => $field) {
            $key = $field['key'] ?? '';
            $isBuiltin = in_array($key, TeamFormField::BUILTIN_KEYS, true);

            if (! $isBuiltin && ! str_starts_with($key, 'custom_')) {
                $validator->errors()->add("fields.{$index}.key", __('team.forms.errors.invalid_field'));
            }

            if (($field['type'] ?? null) === 'select' && empty($field['options'])) {
                $validator->errors()->add("fields.{$index}.options", __('team.forms.errors.options_required'));
            }
        }
    }
}
