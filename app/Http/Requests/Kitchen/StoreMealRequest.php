<?php

namespace App\Http\Requests\Kitchen;

use App\Http\Requests\Kitchen\Concerns\MealRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreMealRequest extends FormRequest
{
    use MealRules;
}
