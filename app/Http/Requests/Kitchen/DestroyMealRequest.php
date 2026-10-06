<?php

namespace App\Http\Requests\Kitchen;

use App\Http\Requests\Kitchen\Concerns\AuthorizesMeal;
use App\Services\MealService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DestroyMealRequest extends FormRequest
{
    use AuthorizesMeal;

    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (app(MealService::class)->deletionErrors($this->route('meal')) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }];
    }
}
