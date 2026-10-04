<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\ShiftBreakRules;
use App\Http\Requests\Team\Concerns\ShiftRules;
use App\Http\Requests\Team\Concerns\ShiftSlotRules;
use App\Support\EventContext;
use App\Support\ShiftCopyAssignments;
use App\Support\ShiftReturnContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreShiftRequest extends FormRequest
{
    use ShiftBreakRules;
    use ShiftRules;
    use ShiftSlotRules { after as slotAfter;
        messages as slotMessages; }

    public function after(): array
    {
        return [...$this->slotAfter(), ...$this->breakAfter(), function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            try {
                ShiftCopyAssignments::resolve($this->route('event'), $this->validated());
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        }];
    }

    public function messages(): array
    {
        return [...$this->slotMessages(), ...$this->breakMessages()];
    }

    public function authorize(): bool
    {
        if (! Gate::allows('scheduling.edit')) {
            return false;
        }
        app(EventContext::class)->requireCurrentEvent($this->user(), $this->route('event'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->shiftRules($this->route('event')),
            'copy' => ['required_with:assignments.*.team_engagement_id', 'nullable', 'integer', Rule::exists('shifts', 'id')->where('event_id', $this->route('event')->id)],
            'assignments' => ['sometimes', 'array', 'list'],
            'assignments.*' => ['required', 'array:team_engagement_id,slot_index,hours_mode,starts_at,ends_at'],
            'assignments.*.team_engagement_id' => ['required', 'integer'],
            'assignments.*.slot_index' => ['present', 'nullable', 'integer', 'min:0'],
            'assignments.*.hours_mode' => ['required', Rule::in(['full_shift', 'custom'])],
            'assignments.*.starts_at' => ['required_if:assignments.*.hours_mode,custom', 'prohibited_if:assignments.*.hours_mode,full_shift', 'date_format:Y-m-d\TH:i'],
            'assignments.*.ends_at' => ['required_if:assignments.*.hours_mode,custom', 'prohibited_if:assignments.*.hours_mode,full_shift', 'date_format:Y-m-d\TH:i', 'after:assignments.*.starts_at'],
            ...$this->slotRules(),
            ...$this->breakRules(),
            ...ShiftReturnContext::rules(),
        ];
    }
}
