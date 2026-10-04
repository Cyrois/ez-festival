<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\ShiftBreakRules;
use App\Http\Requests\Team\Concerns\ShiftRules;
use App\Http\Requests\Team\Concerns\ShiftSlotRules;
use App\Support\ShiftReturnContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreShiftRequest extends FormRequest
{
    use ShiftBreakRules;
    use ShiftRules;
    use ShiftSlotRules { after as slotAfter;
        messages as slotMessages; }

    public function after(): array
    {
        return [...$this->slotAfter(), ...$this->breakAfter()];
    }

    public function messages(): array
    {
        return [...$this->slotMessages(), ...$this->breakMessages()];
    }

    public function authorize(): bool
    {
        return Gate::allows('scheduling.edit');
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
            ...ShiftReturnContext::rules(),
        ];
    }
}
