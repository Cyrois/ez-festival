<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('scheduling.edit');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
