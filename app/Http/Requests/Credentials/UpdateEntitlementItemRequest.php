<?php

namespace App\Http\Requests\Credentials;

use Illuminate\Support\Facades\Gate;

class UpdateEntitlementItemRequest extends StoreEntitlementItemRequest
{
    public function authorize(): bool
    {
        return Gate::allows('credentials.entitlements.edit');
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['opening_balance'], $rules['location_id']);
        $rules['needs_unique_code'] = ['sometimes', 'boolean'];

        return $rules;
    }
}
