<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateShiftTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-shift-templates');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $eventId = $this->route('event')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('event_id', $eventId),
            ],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*.shift_role_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('shift_roles', 'id')->where('event_id', $eventId),
            ],
            'roles.*.headcount' => ['required', 'integer', 'min:1', 'max:65535'],
        ];
    }
}
