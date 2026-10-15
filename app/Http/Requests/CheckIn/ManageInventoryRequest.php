<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Support\Facades\Gate;

class ManageInventoryRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        return Gate::allows('inventory.manage');
    }
}
