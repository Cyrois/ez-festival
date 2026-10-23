<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Validation\Rule;

class InventoryAdjustmentOptionsRequest extends ManageInventoryRequest
{
    public function rules(): array
    {
        return [...parent::rules(),
            'direction' => ['required', Rule::in(['from_location', 'to_location'])],
            'person_id' => ['nullable', 'integer', 'exists:people,id'],
        ];
    }
}
