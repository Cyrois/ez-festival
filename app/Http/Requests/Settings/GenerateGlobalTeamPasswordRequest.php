<?php

namespace App\Http\Requests\Settings;

use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class GenerateGlobalTeamPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-team');
    }

    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Person $person */
            $person = $this->route('person');

            if (! $person->can_log_in) {
                $validator->errors()->add('can_log_in', __('settings.team.login.must_be_enabled'));
            }
        }];
    }
}
