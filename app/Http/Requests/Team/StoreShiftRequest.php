<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\ShiftRules;
use App\Http\Requests\Team\Concerns\ShiftSlotRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreShiftRequest extends FormRequest
{
    use ShiftRules;
    use ShiftSlotRules;

    public function authorize(): bool
    {
        return Gate::allows('manage-team');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->shiftRules($this->route('event')), ...$this->slotRules()];
    }
}
