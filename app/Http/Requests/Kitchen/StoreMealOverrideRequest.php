<?php

namespace App\Http\Requests\Kitchen;

use Illuminate\Validation\Rule;

class StoreMealOverrideRequest extends IndexMealOverridesRequest
{
    public function rules(): array
    {
        return [
            'meal_id' => ['required', 'integer', Rule::exists('meals', 'id')->where('event_id', $this->route('event')->id)],
            'confirmed' => ['required', 'accepted'],
        ];
    }
}
