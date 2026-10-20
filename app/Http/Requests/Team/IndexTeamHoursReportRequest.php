<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexTeamHoursReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('team.hours_report.view');
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'required_with:end_date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'required_with:start_date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required_with' => __('team.reports.range_required'),
            'end_date.required_with' => __('team.reports.range_required'),
            'start_date.date_format' => __('team.reports.range_invalid'),
            'end_date.date_format' => __('team.reports.range_invalid'),
            'end_date.after_or_equal' => __('team.reports.range_order'),
        ];
    }
}
