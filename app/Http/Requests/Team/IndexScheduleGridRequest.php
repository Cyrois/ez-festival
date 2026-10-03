<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexScheduleGridRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('scheduling.view');
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
