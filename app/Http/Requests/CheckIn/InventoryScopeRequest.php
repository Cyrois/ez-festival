<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Validation\Rule;

class InventoryScopeRequest extends HoldingsRequest
{
    public function rules(): array
    {
        return [...InventoryRequest::rules(), 'type' => ['required', Rule::in(['shift', 'group'])]];
    }
}
