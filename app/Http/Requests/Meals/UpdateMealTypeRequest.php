<?php

namespace App\Http\Requests\Meals;

use App\Http\Requests\Meals\Concerns\MealTypeRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMealTypeRequest extends FormRequest
{
    use MealTypeRules;
}
