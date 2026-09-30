<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class LookupGlobalTeamPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-global-team');
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255']];
    }
}
