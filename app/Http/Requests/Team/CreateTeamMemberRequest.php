<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('team.edit');
    }

    public function rules(): array
    {
        return [];
    }
}
