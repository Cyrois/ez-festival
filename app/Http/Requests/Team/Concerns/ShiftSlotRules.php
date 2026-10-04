<?php

namespace App\Http\Requests\Team\Concerns;

use App\Models\Shift;
use App\Support\ShiftSlotReferences;
use Illuminate\Validation\Validator;

trait ShiftSlotRules
{
    protected function slotRules(): array
    {
        return [
            'slots' => ['sometimes', 'array', 'list'],
            'slots.*' => ['required', 'array:id,role_id,needed,client_key'],
            'slots.*.id' => $this->route('shift') instanceof Shift
                ? ['nullable', 'integer', 'distinct']
                : ['prohibited'],
            'slots.*.client_key' => ['sometimes', 'prohibits:slots.*.id', 'string', 'distinct', 'max:64', 'regex:/^draft-\d+$/'],
            'slots.*.role_id' => ['required', 'integer'],
            'slots.*.needed' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'total_needs' => ['prohibited'],
            'filled_count' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->has('slots')) {
                return;
            }

            $shift = $this->route('shift');
            $existing = $shift instanceof Shift ? $shift->roleSlots()->get()->keyBy('id') : collect();
            foreach (ShiftSlotReferences::errors($this->input('slots'), $existing) as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        }];
    }

    public function messages(): array
    {
        return [
            'slots.*.needed.required' => __('team.scheduling.slots.errors.needed'),
            'slots.*.needed.integer' => __('team.scheduling.slots.errors.needed'),
            'slots.*.needed.min' => __('team.scheduling.slots.errors.needed'),
            'slots.*.role_id.required' => __('team.scheduling.slots.errors.role_required'),
        ];
    }
}
