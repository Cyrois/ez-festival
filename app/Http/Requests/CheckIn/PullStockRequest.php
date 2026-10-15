<?php

namespace App\Http\Requests\CheckIn;

use App\Queries\InventoryTargetsQuery;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class PullStockRequest extends ReturnStockRequest
{
    public function authorize(): bool
    {
        return Gate::allows('inventory.manage');
    }

    public function rules(): array
    {
        return [...parent::rules(), 'person_id' => ['required', 'integer', 'exists:people,id']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && ! app(InventoryTargetsQuery::class)->canReceive($this->route('event'), $this->integer('person_id'))) {
                $validator->errors()->add('person_id', __('inventory.errors.receiver_unavailable'));
            }
        }];
    }
}
