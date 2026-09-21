<?php

namespace App\Http\Requests\Credentials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyEntitlementItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-credentials');
    }

    public function rules(): array
    {
        return [];
    }
}
