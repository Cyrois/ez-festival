<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class EditTeamFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-team');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
