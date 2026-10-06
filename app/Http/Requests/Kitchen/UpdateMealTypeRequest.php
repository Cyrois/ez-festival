<?php

namespace App\Http\Requests\Kitchen;

use App\Http\Requests\Kitchen\Concerns\MealTypeRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMealTypeRequest extends FormRequest
{
    use MealTypeRules;
}
