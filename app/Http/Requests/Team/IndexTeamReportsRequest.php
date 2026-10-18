<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexTeamReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('team.hours_report.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
