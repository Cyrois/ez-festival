<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ViewCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('checkin.view');
    }

    public function rules(): array
    {
        return [
            'person' => ['nullable', 'integer', 'exists:people,id'],
        ];
    }
}
