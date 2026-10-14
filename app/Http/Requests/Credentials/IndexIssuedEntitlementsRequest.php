<?php

namespace App\Http\Requests\Credentials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexIssuedEntitlementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view-entitlements');
    }

    public function rules(): array
    {
        return [
            'draw' => ['required', 'integer', 'min:0'],
            'start' => ['required', 'integer', 'min:0'],
            'length' => ['required', 'integer', 'between:1,100'],
        ];
    }
}
