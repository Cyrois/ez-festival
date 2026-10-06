<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\ShiftBreakRules;
use App\Http\Requests\Team\Concerns\ShiftRosterChangeRules;
use App\Http\Requests\Team\Concerns\ShiftRules;
use App\Http\Requests\Team\Concerns\ShiftSlotRules;
use App\Models\Shift;
use App\Support\EventContext;
use App\Support\ShiftReturnContext;
use App\Support\ShiftRosterChanges;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreShiftRequest extends FormRequest
{
    use ShiftBreakRules;
    use ShiftRosterChangeRules;
    use ShiftRules;
    use ShiftSlotRules { after as slotAfter;
        messages as slotMessages; }

    public function after(): array
    {
        return [...$this->slotAfter(), ...$this->breakAfter(), function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $shift = new Shift(['event_id' => $this->route('event')->id]);
            foreach (ShiftRosterChanges::errors($shift, $this->all()) as $key => $message) {
                $validator->errors()->add($key, $message);
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
            ...$this->slotRules(),
            ...$this->breakRules(),
            ...$this->rosterChangeRules(),
            ...ShiftReturnContext::rules(),
        ];
    }
}
