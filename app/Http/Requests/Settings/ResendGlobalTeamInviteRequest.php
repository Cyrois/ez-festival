<?php

namespace App\Http\Requests\Settings;

use App\Models\Person;
use App\Services\LoginInvitationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class ResendGlobalTeamInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-global-team');
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

            if (! app(LoginInvitationService::class)->canResend($person)) {
                $validator->errors()->add('invite', __('settings.team.login.invite_unavailable'));
            }
        }];
    }
}
