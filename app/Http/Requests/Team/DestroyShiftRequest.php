<?php

namespace App\Http\Requests\Team;

use App\Support\EventContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class DestroyShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Gate::allows('scheduling.edit')) {
            return false;
        }
        app(EventContext::class)->requireCurrentEvent($this->user(), $this->route('event'));
        abort_unless((int) $this->route('shift')->event_id === (int) $this->route('event')->id, 404);

        return true;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->route('shift')->assignments()->count() !== (int) $this->input('assignment_count', 0)) {
                $validator->errors()->add('assignment_count', __('team.scheduling.assignments.errors.stale_delete'));
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['assignment_count' => ['sometimes', 'integer', 'min:0']];
    }
}
