<?php

namespace App\Http\Requests\Credentials;

class UpdateEventStockItemRequest extends StoreEventStockItemRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['opening_balance']);

        return $rules;
    }
}
