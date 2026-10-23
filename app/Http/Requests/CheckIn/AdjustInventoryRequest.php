<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Validation\Rule;

class AdjustInventoryRequest extends ManageInventoryRequest
{
    public function rules(): array
    {
        $event = $this->route('event');

        return [
            'direction' => ['required', Rule::in(['from_location', 'to_location'])],
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('event_id', $event->id)],
            'note' => ['nullable', 'prohibited_if:direction,from_location', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['array:entitlement_item_id,quantity'],
            'items.*.entitlement_item_id' => ['required', 'integer', 'distinct', Rule::exists('entitlement_items', 'id')->where('event_id', $event->id)],
            'items.*.quantity' => ['required', 'integer', 'between:1,2147483647'],
        ];
    }
}
