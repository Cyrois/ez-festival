<?php

namespace App\Http\Requests\Settings\Concerns;

use App\Models\Role;
use App\Repositories\RoleRepository;
use App\Support\RoleName;
use Illuminate\Validation\Validator;

trait ValidatesRoleName
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => RoleName::clean($this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'can_read_team_notes' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('settings.roles.validation.name_required'),
        ];
    }

    /**
     * Names are unique across the organization, off roles included. The clash check runs on the
     * comparison key (see RoleName), not on the raw name, so it does not depend on DB collation.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('name')) {
                    return;
                }

                $clash = app(RoleRepository::class)->findByName($this->input('name'), $this->ignoredRole());

                if ($clash !== null) {
                    $validator->errors()->add('name', __('settings.roles.validation.name_taken'));
                    // The existing name, so the form can show which role it matches.
                    $validator->errors()->add('name_match', $clash->name);
                }
            },
        ];
    }

    abstract protected function ignoredRole(): ?Role;
}
