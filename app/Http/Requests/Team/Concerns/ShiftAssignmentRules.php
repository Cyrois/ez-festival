<?php

namespace App\Http\Requests\Team\Concerns;

use App\Rules\EventLocalTimeRule;
use App\Support\EventContext;
use App\Support\ShiftAssignmentHours;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

trait ShiftAssignmentRules
{
    protected function assignmentRules(): array
    {
        $shift = $this->route('shift');

        return [
            'shift_role_slot_id' => ['required', 'integer', Rule::exists('shift_role_slots', 'id')->where('shift_id', $shift->id)],
            ...$this->hoursRules(),
        ];
    }

    protected function hoursRules(): array
    {
        return [
            'hours_mode' => ['required', Rule::in(['full_shift', 'custom'])],
            'starts_at' => ['required_if:hours_mode,custom', 'prohibited_if:hours_mode,full_shift', 'date_format:Y-m-d\TH:i', new EventLocalTimeRule(($this->route('event') ?? $this->route('shift')->event)->timezone)],
            'ends_at' => ['required_if:hours_mode,custom', 'prohibited_if:hours_mode,full_shift', 'date_format:Y-m-d\TH:i', new EventLocalTimeRule(($this->route('event') ?? $this->route('shift')->event)->timezone), 'after:starts_at'],
        ];
    }

    protected function authorizeShift(): bool
    {
        if (! Gate::allows('scheduling.edit')) {
            return false;
        }
        $shift = $this->route('shift');
        $event = app(EventContext::class)->requireCurrent($this->user());
        abort_unless((int) $shift->event_id === (int) $event->id, 404);
        if ($this->route('event')) {
            abort_unless($this->route('event')->is($event), 404);
        }
        if ($this->route('assignment')) {
            abort_unless((int) $this->route('assignment')->shift_id === (int) $shift->id, 404);
        }

        return true;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), [...array_keys($this->rules()), '_token', '_method']) as $key) {
                $validator->errors()->add($key, __('team.scheduling.assignments.errors.unexpected'));
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            try {
                ShiftAssignmentHours::resolve($this->route('shift'), $this->all());
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        }];
    }
}
