<?php

namespace App\Http\Requests\Credentials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyPassAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('pass-assignment.edit');
    }

    public function rules(): array
    {
        return [];
    }
}
