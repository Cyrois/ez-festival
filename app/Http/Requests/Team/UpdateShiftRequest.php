<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\Team\Concerns\ShiftRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateShiftRequest extends FormRequest
{
    use ShiftRules;

    public function authorize(): bool
    {
        return Gate::allows('team.edit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->shiftRules($this->route('event'));
    }
}
