<?php

namespace App\Http\Requests\CheckIn;

use App\Queries\InventoryTargetsQuery;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class PassStockRequest extends StockMovementRequest
{
    public function authorize(): bool
    {
        return Gate::allows('pass-inventory-stock');
    }

    public function rules(): array
    {
        return [...parent::rules(), 'person_id' => ['required', 'integer', 'exists:people,id']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && ! app(InventoryTargetsQuery::class)->canPassTo($this->route('event'), $this->user(), $this->integer('person_id'))) {
                $validator->errors()->add('person_id', __('inventory.errors.pass_unavailable'));
            }
        }];
    }
}
