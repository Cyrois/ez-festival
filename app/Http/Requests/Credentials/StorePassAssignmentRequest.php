<?php

namespace App\Http\Requests\Credentials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePassAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-credentials');
    }

    public function rules(): array
    {
        return [
            'pass_type_id' => ['required', 'integer', Rule::exists('pass_types', 'id')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
