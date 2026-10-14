<?php

namespace App\Http\Requests\Credentials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreatePassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('credentials.passes.edit');
    }

    public function rules(): array
    {
        return [];
    }
}
