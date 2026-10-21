<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexShiftHoursPeopleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('team.hours_report.view');
    }

    public function rules(): array
    {
        return [];
    }
}
