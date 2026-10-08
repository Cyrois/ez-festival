<?php

namespace App\Http\Requests\Kitchen;

class ShowKitchenPersonRequest extends IndexMealsRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'date' => ['sometimes', 'required', 'date_format:Y-m-d']];
    }
}
