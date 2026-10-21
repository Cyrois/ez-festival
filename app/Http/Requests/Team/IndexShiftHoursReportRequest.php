<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexShiftHoursReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('team.hours_report.view');
    }

    public function rules(): array
    {
        return ['date' => ['required', 'date_format:Y-m-d']];
    }

    public function messages(): array
    {
        return ['date.required' => __('team.reports.day_required'), 'date.date_format' => __('team.reports.range_invalid')];
    }
}
