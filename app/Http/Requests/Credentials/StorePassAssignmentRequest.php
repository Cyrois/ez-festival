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
            'pass_id' => ['required', 'integer', Rule::exists('passes', 'id')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
