<?php

namespace App\Http\Requests\Team;

use App\Support\EventContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Gate::allows('scheduling.edit')) {
            return false;
        }
        $event = app(EventContext::class)->requireCurrent($this->user());
        $shift = $this->route('shift');
        abort_unless($this->route('event')->is($event) && (int) $shift->event_id === (int) $event->id
            && (int) $this->route('assignment')->shift_id === (int) $shift->id, 404);

        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
