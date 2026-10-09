<?php

namespace App\Http\Requests\CheckIn;

class IndexCheckInDataTableRequest extends IndexCheckInRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'draw' => ['required', 'integer', 'min:0'],
            'start' => ['required', 'integer', 'min:0'],
            'length' => ['required', 'integer', 'between:1,100'],
        ];
    }
}
