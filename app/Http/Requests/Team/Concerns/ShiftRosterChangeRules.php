<?php

namespace App\Http\Requests\Team\Concerns;

use App\Support\ShiftBreaks;
use Illuminate\Validation\Rule;

trait ShiftRosterChangeRules
{
    protected function rosterChangeRules(): array
    {
        $shift = $this->route('shift');
        $rules = [
            'supervisor_key' => ['sometimes', 'nullable', 'integer', 'not_in:0'],
            'assignment_updates' => $shift ? ['sometimes', 'array', 'list'] : ['prohibited'],
            'assignment_updates.*' => ['required', 'array:id,hours_mode,starts_at,ends_at,breaks'],
            'assignment_updates.*.id' => ['required', 'integer', 'distinct', Rule::exists('shift_assignments', 'id')->where('shift_id', $shift?->id)],
            'assignment_removals' => $shift ? ['sometimes', 'array', 'list'] : ['prohibited'],
            'assignment_removals.*' => ['required', 'integer', 'distinct', Rule::exists('shift_assignments', 'id')->where('shift_id', $shift?->id)],
            'assignment_additions' => ['sometimes', 'array', 'list'],
            'assignment_additions.*' => ['required', 'array:extra,shift_role_slot_id,slot_key,role_id,team_engagement_id,hours_mode,starts_at,ends_at,breaks,client_key'],
            'assignment_additions.*.extra' => ['sometimes', 'boolean', 'accepted', 'prohibits:assignment_additions.*.shift_role_slot_id,assignment_additions.*.slot_key,assignment_additions.*.role_id'],
            'assignment_additions.*.shift_role_slot_id' => $shift
                ? ['required_without_all:assignment_additions.*.slot_key,assignment_additions.*.role_id,assignment_additions.*.extra', 'prohibits:assignment_additions.*.slot_key', 'integer', Rule::exists('shift_role_slots', 'id')->where('shift_id', $shift->id)]
                : ['prohibited'],
            'assignment_additions.*.slot_key' => ['required_without_all:assignment_additions.*.shift_role_slot_id,assignment_additions.*.role_id,assignment_additions.*.extra', 'prohibits:assignment_additions.*.shift_role_slot_id', 'string', 'max:64', 'regex:/^draft-\d+$/'],
            'assignment_additions.*.role_id' => ['required_without_all:assignment_additions.*.slot_key,assignment_additions.*.shift_role_slot_id,assignment_additions.*.extra', 'prohibits:assignment_additions.*.slot_key,assignment_additions.*.shift_role_slot_id', 'integer', Rule::exists('roles', 'id')],
            'assignment_additions.*.team_engagement_id' => ['required', 'integer', 'distinct', Rule::exists('team_engagements', 'id')->where('event_id', $shift?->event_id ?? $this->route('event')->id)->where('status', 'hired')],
        ];
        $rules['assignment_additions.*.client_key'] = ['sometimes', 'integer', 'max:-1', 'distinct'];
        $rules['break_operations'] = ['sometimes', 'array', 'list'];
        $rules['break_operations.*'] = ['required', 'array:type,assignment_key,assignment_keys,starts_at,ends_at,breaks,source,break,apply'];
        $rules['break_operations.*.type'] = ['required', Rule::in(['person', 'default', 'mass'])];
        $rules['break_operations.*.assignment_keys'] = ['required_if:break_operations.*.type,mass', 'array', 'list', 'min:1'];
        $rules['break_operations.*.assignment_keys.*'] = ['required', 'integer'];
        $rules['break_operations.*.assignment_key'] = ['required_if:break_operations.*.type,person', 'integer'];
        foreach (['starts_at', 'ends_at'] as $field) {
            $rules["break_operations.*.$field"] = ['required_if:break_operations.*.type,person', 'date_format:Y-m-d\\TH:i'];
        }
        $rules['break_operations.*.source'] = ['required_if:break_operations.*.type,default', 'string', 'max:64', 'regex:/^(?:[1-9]\d*|draft-break-\d+)$/'];
        $rules['break_operations.*.apply'] = ['required_if:break_operations.*.type,default', 'boolean'];
        $rules['break_operations.*.break'] = ['present_if:break_operations.*.type,default', 'required_if:break_operations.*.type,mass', 'nullable', 'array:duration_minutes,starts_at'];
        $rules['break_operations.*.break.duration_minutes'] = ['required_with:break_operations.*.break', 'integer', Rule::in(ShiftBreaks::DURATIONS)];
        $rules['break_operations.*.break.starts_at'] = ['required_with:break_operations.*.break', 'date_format:Y-m-d\\TH:i'];
        foreach (['assignment_updates', 'assignment_additions', 'break_operations'] as $field) {
            $rules["$field.*.breaks"] = ['sometimes', 'array', 'list'];
            $rules["$field.*.breaks.*"] = ['required', 'array:id,shift_break_id,shift_break_key,duration_minutes,starts_at'];
            $rules["$field.*.breaks.*.id"] = $field === 'assignment_additions' ? ['prohibited'] : ['nullable', 'integer'];
            $rules["$field.*.breaks.*.shift_break_id"] = ['nullable', 'integer', "prohibits:$field.*.breaks.*.shift_break_key"];
            $rules["$field.*.breaks.*.shift_break_key"] = ['sometimes', 'string', 'max:64', 'regex:/^draft-break-\d+$/'];
            $rules["$field.*.breaks.*.duration_minutes"] = ['required', 'integer', Rule::in(ShiftBreaks::DURATIONS)];
            $rules["$field.*.breaks.*.starts_at"] = ['required', 'date_format:Y-m-d\\TH:i'];
        }
        // Intermediate personal drafts may be invalid; only their final Save state must pass.
        $rules['break_operations.*.breaks.*.starts_at'] = ['present', 'nullable', 'string'];
        $rules['break_operations.*.breaks.*.duration_minutes'] = ['present', 'integer'];
        $rules['break_operations.*.breaks'] = ['present_if:break_operations.*.type,person', 'array', 'list'];
        foreach (['assignment_updates', 'assignment_additions'] as $field) {
            $rules["$field.*.hours_mode"] = ['required', Rule::in(['full_shift', 'custom'])];
            $rules["$field.*.starts_at"] = ["required_if:$field.*.hours_mode,custom", "prohibited_if:$field.*.hours_mode,full_shift", 'date_format:Y-m-d\TH:i'];
            $rules["$field.*.ends_at"] = ["required_if:$field.*.hours_mode,custom", "prohibited_if:$field.*.hours_mode,full_shift", 'date_format:Y-m-d\TH:i', "after:$field.*.starts_at"];
        }

        return $rules;
    }
}
