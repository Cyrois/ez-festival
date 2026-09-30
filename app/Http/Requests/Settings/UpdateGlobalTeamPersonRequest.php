<?php

namespace App\Http\Requests\Settings;

use App\Models\Event;
use App\Services\GlobalTeamService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGlobalTeamPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-team');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'can_log_in' => ['required', 'boolean'],
            'is_admin' => ['sometimes', 'boolean'],
            'event_access' => ['required', 'array'],
            'event_access.*.event_id' => ['required', 'integer', 'distinct', Rule::exists('events', 'id')],
            'event_access.*.role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'event_access.*.status' => ['nullable', Rule::in(['applied', 'reviewing', 'hired'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $submitted = collect($this->input('event_access'))->pluck('event_id')->sort()->values();
            $events = Event::query()->orderBy('id')->pluck('id')->sort()->values();

            if ($submitted->all() !== $events->all()) {
                $validator->errors()->add('event_access', __('settings.team.validation.all_events_required'));
            }

            $this->validateAdminChange($validator);

            if ($this->boolean('can_log_in')) {
                return;
            }

            $reason = app(GlobalTeamService::class)->loginLockReason(
                $this->user(),
                $this->route('person'),
            );

            if ($reason !== null) {
                $validator->errors()->add('can_log_in', $reason);
            }
        }];
    }

    private function validateAdminChange(Validator $validator): void
    {
        if (! $this->has('is_admin')) {
            return;
        }

        $person = $this->route('person');
        $target = $person->user()->first();
        $current = (bool) $target?->is_admin;

        if ($this->boolean('is_admin') === $current) {
            return;
        }

        if (! $this->user()->isAdmin()) {
            $validator->errors()->add('is_admin', __('settings.team.admin.unauthorized'));

            return;
        }

        if ($target === null) {
            $validator->errors()->add('is_admin', __('settings.team.admin.login_required'));

            return;
        }

        if (! $this->boolean('is_admin') && (int) $this->user()->getKey() === (int) $target->getKey()) {
            $validator->errors()->add('is_admin', __('settings.team.admin.own_disabled'));
        }
    }
}
