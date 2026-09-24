<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyShiftRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-shift-templates');
    }

    public function rules(): array
    {
        return [];
    }
}
