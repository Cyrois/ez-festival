<?php

namespace App\Http\Requests\Credentials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexIssuedEntitlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view-credentials');
    }

    public function rules(): array
    {
        return ['draw' => ['sometimes', 'integer', 'min:0'], 'start' => ['sometimes', 'integer', 'min:0'], 'length' => ['sometimes', 'integer', 'between:1,100'], 'search' => ['sometimes', 'array'], 'search.value' => ['nullable', 'string', 'max:255']];
    }
}
