<?php

namespace App\Http\Requests\Team\Concerns;

use Illuminate\Validation\Rule;

trait ShiftRosterChangeRules
{
    protected function rosterChangeRules(): array
    {
        $shift = $this->route('shift');
        $rules = [
            'assignment_updates' => $shift ? ['sometimes', 'array', 'list'] : ['prohibited'],
            'assignment_updates.*' => ['required', 'array:id,hours_mode,starts_at,ends_at'],
            'assignment_updates.*.id' => ['required', 'integer', 'distinct', Rule::exists('shift_assignments', 'id')->where('shift_id', $shift?->id)],
            'assignment_removals' => $shift ? ['sometimes', 'array', 'list'] : ['prohibited'],
            'assignment_removals.*' => ['required', 'integer', 'distinct', Rule::exists('shift_assignments', 'id')->where('shift_id', $shift?->id)],
            'assignment_additions' => ['sometimes', 'array', 'list'],
            'assignment_additions.*' => ['required', 'array:shift_role_slot_id,slot_key,team_engagement_id,hours_mode,starts_at,ends_at'],
            'assignment_additions.*.shift_role_slot_id' => $shift
                ? ['required_without:assignment_additions.*.slot_key', 'prohibits:assignment_additions.*.slot_key', 'integer', Rule::exists('shift_role_slots', 'id')->where('shift_id', $shift->id)]
                : ['prohibited'],
            'assignment_additions.*.slot_key' => ['required_without:assignment_additions.*.shift_role_slot_id', 'prohibits:assignment_additions.*.shift_role_slot_id', 'string', 'max:64', 'regex:/^draft-\d+$/'],
            'assignment_additions.*.team_engagement_id' => ['required', 'integer', 'distinct', Rule::exists('team_engagements', 'id')->where('event_id', $shift?->event_id ?? $this->route('event')->id)->where('status', 'hired')],
        ];
        foreach (['assignment_updates', 'assignment_additions'] as $field) {
            $rules["$field.*.hours_mode"] = ['required', Rule::in(['full_shift', 'custom'])];
            $rules["$field.*.starts_at"] = ["required_if:$field.*.hours_mode,custom", "prohibited_if:$field.*.hours_mode,full_shift", 'date_format:Y-m-d\TH:i'];
            $rules["$field.*.ends_at"] = ["required_if:$field.*.hours_mode,custom", "prohibited_if:$field.*.hours_mode,full_shift", 'date_format:Y-m-d\TH:i', "after:$field.*.starts_at"];
        }

        return $rules;
    }
}
