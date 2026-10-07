<?php

namespace App\Http\Requests\Team\Concerns;

use App\Models\Shift;
use App\Support\ShiftMeals;
use Illuminate\Validation\Validator;

trait ShiftMealRules
{
    protected function mealRules(): array
    {
        $rules = [
            'meals' => ['sometimes', 'array', 'list'],
            'meals.*' => ['required', 'array:meal_id,assignment_keys'],
            'meals.*.meal_id' => ['required', 'integer', 'distinct'],
            'meals.*.assignment_keys' => ['required', 'array', 'list', 'min:1'],
            'meals.*.assignment_keys.*' => ['required', 'integer'],
        ];

        return $rules;
    }

    protected function mealAfter(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $shift = $this->route('shift') ?? new Shift(['event_id' => $this->route('event')->id]);
            foreach (ShiftMeals::errors($shift, $validator->validated()) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }];
    }

    protected function mealMessages(): array
    {
        return [
            'meals.*.meal_id.distinct' => __('team.scheduling.meals.errors.duplicate'),
            'meals.*.assignment_keys.required' => __('team.scheduling.meals.errors.empty'),
            'meals.*.assignment_keys.min' => __('team.scheduling.meals.errors.empty'),
        ];
    }
}
