<?php

namespace App\Http\Requests\Settings;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGlobalTeamPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
        }];
    }
}
