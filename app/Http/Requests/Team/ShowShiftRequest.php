<?php

namespace App\Http\Requests\Team;

use App\Support\ShiftReturnContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ShowShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('scheduling.view');
    }

    public function rules(): array
    {
        return ShiftReturnContext::rules();
    }
}
