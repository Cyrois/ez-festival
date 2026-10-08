<?php

namespace App\Http\Requests\Kitchen;

use App\Support\EventContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClaimMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = app(EventContext::class)->requireCurrentEvent($this->user(), $this->route('event'));
        abort_unless((int) $this->route('member')->event_id === (int) $event->id && $this->route('member')->status === 'hired', 404);

        return $this->user()->can('meals.claim', $event);
    }

    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer', Rule::exists('meal_assignments', 'id')
                ->where('event_id', $this->route('event')->id)
                ->where('team_engagement_id', $this->route('member')->id)],
            'confirm_warning' => ['sometimes', 'boolean'],
        ];
    }
}
