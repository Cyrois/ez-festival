<?php

namespace App\Http\Requests\CheckIn;

use App\Queries\InventoryTargetsQuery;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReturnStockRequest extends StockMovementRequest
{
    public function rules(): array
    {
        $eventId = $this->route('event')->id;

        return [
            ...parent::rules(),
            'location_id' => ['nullable', 'required_without:person_id', 'prohibits:person_id', 'integer', Rule::exists('locations', 'id')->where('event_id', $eventId)],
            'person_id' => ['nullable', 'required_without:location_id', 'prohibits:location_id', 'integer', 'exists:people,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->filled('person_id')
                && ! app(InventoryTargetsQuery::class)->isManagerTarget($this->route('event'), $this->integer('person_id'), $this->user()->person_id)) {
                $validator->errors()->add('person_id', __('inventory.errors.manager_unavailable'));
            }
        }];
    }
}
