<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\TeamMemberRules;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeamMemberRequest extends FormRequest
{
    use TeamMemberRules;

    public function authorize(): bool
    {
        return Gate::allows('manage-team');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $eventId = (int) $this->route('engagement')->event_id;

        return [
            ...$this->memberRules($eventId),
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'pass_assignments' => ['sometimes', 'array'],
            'pass_assignments.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('pass_assignments', 'id'),
            ],
            'pass_assignments.*.pass_type_id' => [
                'required',
                'integer',
                Rule::exists('pass_types', 'id')->where('event_id', $eventId),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('role_id') || $this->input('role_id') === null) {
                return;
            }

            $engagement = $this->route('engagement');
            $roleId = (int) $this->input('role_id');

            if ($roleId === (int) $engagement->role_id) {
                return;
            }

            if (! Role::query()->whereKey($roleId)->where('active', true)->exists()) {
                $validator->errors()->add('role_id', __('settings.team.validation.role_unavailable'));
            }
        }];
    }

    public function messages(): array
    {
        return [
            'pass_assignments.*.pass_type_id.exists' => __('team.member.passes.errors.foreign_pass'),
            'pass_assignments.*.pass_type_id.required' => __('team.member.passes.errors.pass_required'),
        ];
    }
}
