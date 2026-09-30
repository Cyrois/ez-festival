<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreGlobalTeamPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-team');
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
