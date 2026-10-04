<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\ShiftAssignmentRules;
use App\Models\Shift;
use App\Support\ShiftAssignmentHours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class IndexShiftAssignmentOverlapsRequest extends FormRequest
{
    use ShiftAssignmentRules;

    public function authorize(): bool
    {
        return $this->authorizeShift();
    }

    public function proposedShift(): Shift
    {
        $shift = clone $this->route('shift');
        if ($this->filled('shift_starts_at') && $this->filled('shift_ends_at')) {
            $shift->starts_at = $this->input('shift_starts_at');
            $shift->ends_at = $this->input('shift_ends_at');
        }

        return $shift;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $key) {
                $validator->errors()->add($key, __('team.scheduling.assignments.errors.unexpected'));
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            try {
                ShiftAssignmentHours::resolve($this->proposedShift(), $this->all());
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    $validator->errors()->add($key, $messages[0]);
                }
            }
        }];
    }

    public function rules(): array
    {
        return [...$this->hoursRules(),
            'shift_starts_at' => ['required_with:shift_ends_at', 'date_format:Y-m-d\TH:i'],
            'shift_ends_at' => ['required_with:shift_starts_at', 'date_format:Y-m-d\TH:i', 'after:shift_starts_at'],
            'team_engagement_id' => $this->route('assignment') ? ['prohibited'] : ['required', 'integer', Rule::exists('team_engagements', 'id')->where('event_id', $this->route('shift')->event_id)->where('status', 'hired')],
        ];
    }
}
