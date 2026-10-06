<?php

namespace App\Http\Requests\Team\Concerns;

use App\Models\Shift;
use App\Support\ShiftBreaks;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ShiftBreakRules
{
    protected function breakRules(): array
    {
        return [
            'breaks' => ['sometimes', 'array', 'list'],
            'breaks.*' => ['required', 'array:id,client_key,duration_minutes,starts_at'],
            'breaks.*.id' => $this->route('shift') instanceof Shift ? ['nullable', 'integer', 'distinct'] : ['prohibited'],
            'breaks.*.client_key' => ['sometimes', 'string', 'max:64', 'distinct', 'regex:/^draft-break-\d+$/'],
            'breaks.*.duration_minutes' => ['required', 'integer', Rule::in(ShiftBreaks::DURATIONS)],
            'breaks.*.starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
        ];
    }

    protected function breakAfter(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $shift = $this->route('shift');
            $existing = $shift instanceof Shift ? $shift->breaks()->get()->keyBy('id') : collect();
            foreach (ShiftBreaks::errors($this->all(), $existing) as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        }];
    }

    protected function breakMessages(): array
    {
        $personal = [];
        foreach (['assignment_updates', 'assignment_additions'] as $field) {
            foreach (['required', 'date_format'] as $rule) {
                $personal["$field.*.breaks.*.starts_at.$rule"] = __('team.scheduling.breaks.errors.start');
            }
            foreach (['required', 'integer', 'in'] as $rule) {
                $personal["$field.*.breaks.*.duration_minutes.$rule"] = __('team.scheduling.breaks.errors.duration');
            }
            $personal["$field.*.breaks.*.id.integer"] = __('team.scheduling.breaks.errors.foreign');
            $personal["$field.*.breaks.*.id.prohibited"] = __('team.scheduling.breaks.errors.foreign');
        }

        return [
            ...$personal,
            'breaks.array' => __('team.scheduling.breaks.errors.collection'),
            'breaks.list' => __('team.scheduling.breaks.errors.collection'),
            'breaks.*.array' => __('team.scheduling.breaks.errors.row'),
            'breaks.*.id.prohibited' => __('team.scheduling.breaks.errors.foreign'),
            'breaks.*.id.integer' => __('team.scheduling.breaks.errors.foreign'),
            'breaks.*.id.distinct' => __('team.scheduling.breaks.errors.foreign'),
            'breaks.*.duration_minutes.required' => __('team.scheduling.breaks.errors.duration'),
            'breaks.*.duration_minutes.integer' => __('team.scheduling.breaks.errors.duration'),
            'breaks.*.duration_minutes.in' => __('team.scheduling.breaks.errors.duration'),
            'breaks.*.starts_at.required' => __('team.scheduling.breaks.errors.start'),
            'breaks.*.starts_at.date_format' => __('team.scheduling.breaks.errors.start'),
        ];
    }
}
