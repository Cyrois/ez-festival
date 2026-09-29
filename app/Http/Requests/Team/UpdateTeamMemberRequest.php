<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\TeamMemberRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    use TeamMemberRules;

    public function authorize(): bool
    {
        return Gate::allows('manage-team');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $eventId = (int) $this->route('engagement')->event_id;

        return [
            ...$this->memberRules($eventId),
            'pass_assignments' => ['sometimes', 'array'],
            'pass_assignments.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('pass_assignments', 'id'),
            ],
            'pass_assignments.*.pass_type_id' => [
                'required',
                'integer',
                Rule::exists('pass_types', 'id')->where('event_id', $eventId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'pass_assignments.*.pass_type_id.exists' => __('team.member.passes.errors.foreign_pass'),
            'pass_assignments.*.pass_type_id.required' => __('team.member.passes.errors.pass_required'),
        ];
    }
}
