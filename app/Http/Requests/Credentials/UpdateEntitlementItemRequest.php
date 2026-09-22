<?php

namespace App\Http\Requests\Credentials;

class UpdateEntitlementItemRequest extends StoreEntitlementItemRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['opening_balance']);

        return $rules;
    }
}
