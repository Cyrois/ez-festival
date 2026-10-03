<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexSchedulingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('scheduling.view');
    }

    public function rules(): array
    {
        return ['date' => ['sometimes', 'date_format:Y-m-d']];
    }
}
