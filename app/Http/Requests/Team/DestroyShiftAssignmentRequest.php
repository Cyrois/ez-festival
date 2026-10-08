<?php

namespace App\Http\Requests\Team;

use App\Support\EventContext;
use App\Support\ShiftMeals;
use App\Support\ShiftReturnContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

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
        return ShiftReturnContext::rules();
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (ShiftMeals::removalErrors($this->route('shift'), $this->route('assignment')->id) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }];
    }
}
