<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows($this->routeIs('check-in.inventory.index') ? 'view-inventory-holdings' : 'checkin.edit');
    }

    public function rules(): array
    {
        $eventId = $this->user()->effectiveEvent()?->id ?? 0;

        return [
            'draw' => ['sometimes', 'integer', 'min:0'],
            'start' => ['sometimes', 'integer', 'min:0'],
            'length' => ['sometimes', 'integer', 'between:1,100'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'item' => ['nullable', 'integer', Rule::exists('entitlement_items', 'id')->where('event_id', $eventId)],
            'holder_type' => ['nullable', Rule::in(['person', 'location'])],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('event_id', $eventId)],
        ];
    }
}
