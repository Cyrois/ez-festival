<?php

namespace App\Http\Requests\Settings;

use App\Services\PersonService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGlobalTeamPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-global-team');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'can_log_in' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['applied', 'reviewing', 'hired'])],
            'event_access' => ['required', 'array', 'min:1'],
            'event_access.*.event_id' => ['required', 'integer', 'distinct', Rule::exists('events', 'id')->where('locked', 0)],
            'event_access.*.role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('active', 1)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (app(PersonService::class)->findByEmail($this->input('email')) !== null) {
                $validator->errors()->add('email', __('settings.team.validation.email_exists'));
            }
        }];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('settings.team.validation.name_required'),
            'email.required' => __('settings.team.validation.email_required'),
            'event_access.required' => __('settings.team.validation.access_required'),
            'event_access.min' => __('settings.team.validation.access_required'),
            'event_access.*.event_id.exists' => __('settings.team.validation.locked_event'),
            'event_access.*.role_id.exists' => __('settings.team.validation.role_unavailable'),
        ];
    }
}
