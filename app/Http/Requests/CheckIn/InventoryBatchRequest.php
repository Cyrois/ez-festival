<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Validation\Rule;

class InventoryBatchRequest extends HoldingsPersonRequest
{
    public function rules(): array
    {
        $event = $this->route('event');
        $takeBack = $this->routeIs('check-in.inventory.take-backs.store');

        return [...parent::rules(),
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['array:entitlement_item_id,quantity,location_id'],
            'items.*.entitlement_item_id' => ['required', 'integer', 'distinct', Rule::exists('entitlement_items', 'id')->where('event_id', $event->id)],
            'items.*.quantity' => ['required', 'integer', 'min:'.($takeBack ? 0 : 1), 'max:2147483647'],
            'items.*.location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('event_id', $event->id)],
            ...($takeBack ? [
                'destination' => ['required', Rule::in(['me', 'location', 'person'])],
                'location_id' => ['nullable', 'required_if:destination,location', 'prohibited_unless:destination,location', 'integer', Rule::exists('locations', 'id')->where('event_id', $event->id)],
                'receiver_id' => ['nullable', 'required_if:destination,person', 'prohibited_unless:destination,person', 'integer', 'exists:people,id'],
                'note' => ['nullable', 'string', 'max:255'],
            ] : []),
        ];
    }
}
