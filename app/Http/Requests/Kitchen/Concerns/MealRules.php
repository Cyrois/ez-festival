<?php

namespace App\Http\Requests\Kitchen\Concerns;

use App\Services\MealService;
use Illuminate\Validation\Validator;

trait MealRules
{
    use AuthorizesMeal;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'meal_type_id' => ['bail', 'required', 'integer'],
            'date' => ['bail', 'required', 'date_format:Y-m-d'],
            'starts_at' => ['bail', 'required', 'date_format:H:i'],
            'ends_at' => ['bail', 'required', 'date_format:H:i'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('meals.name'),
            'meal_type_id' => __('meals.type'),
            'date' => __('meals.date'),
            'starts_at' => __('meals.start'),
            'ends_at' => __('meals.end'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach (app(MealService::class)->validationErrors(
                $this->route('event'), $validator->validated(), $this->route('meal'),
            ) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }];
    }
}
