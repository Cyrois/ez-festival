<?php

namespace App\Http\Requests\CheckIn;

class HoldingsPersonRequest extends HoldingsRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'person_id' => ['required', 'integer', 'exists:people,id']];
    }
}
