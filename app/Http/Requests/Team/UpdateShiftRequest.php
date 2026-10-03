<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\ShiftBreakRules;
use App\Http\Requests\Team\Concerns\ShiftRules;
use App\Http\Requests\Team\Concerns\ShiftSlotRules;
use App\Support\EventContext;
use App\Support\ShiftAssignmentHours;
use App\Support\ShiftReturnContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateShiftRequest extends FormRequest
{
    use ShiftBreakRules;
    use ShiftRules;
    use ShiftSlotRules { after as slotAfter;
        messages as slotMessages; }

    public function messages(): array
    {
        return [...$this->slotMessages(), ...$this->breakMessages()];
    }

    public function after(): array
    {
        return [...$this->slotAfter(), ...$this->breakAfter(), function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $shift = $this->route('shift');
            foreach (ShiftAssignmentHours::containmentErrors($shift, $this->all()) as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        }];
    }

    public function authorize(): bool
    {
        if (! Gate::allows('scheduling.edit')) {
            return false;
        }
        app(EventContext::class)->requireCurrentEvent($this->user(), $this->route('event'));
        abort_unless((int) $this->route('shift')->event_id === (int) $this->route('event')->id, 404);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->shiftRules($this->route('event')), ...$this->slotRules(), ...$this->breakRules(), ...ShiftReturnContext::rules()];
    }
}
