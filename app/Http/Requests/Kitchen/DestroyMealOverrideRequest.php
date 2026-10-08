<?php

namespace App\Http\Requests\Kitchen;

use Illuminate\Validation\Rule;

class DestroyMealOverrideRequest extends IndexMealOverridesRequest
{
    protected function ability(): string
    {
        return 'meals.undo_claim';
    }

    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer', Rule::exists('meal_assignments', 'id')
                ->where('event_id', $this->route('event')->id)
                ->where('team_engagement_id', $this->route('member')->id)->where('is_override', true)],
            'confirmed' => ['required', 'accepted'],
        ];
    }
}
