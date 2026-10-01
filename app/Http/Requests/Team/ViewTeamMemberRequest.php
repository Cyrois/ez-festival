<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ViewTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('team.view');
    }

    public function rules(): array
    {
        return [];
    }
}
