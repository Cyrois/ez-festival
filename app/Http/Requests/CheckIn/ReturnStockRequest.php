<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReturnStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('checkin.edit');
    }

    public function rules(): array
    {
        $eventId = $this->route('event')->id;

        return [
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('event_id', $eventId)],
            'entitlement_item_id' => ['required', 'integer', Rule::exists('entitlement_items', 'id')->where('event_id', $eventId)],
            'quantity' => ['required', 'integer', 'between:1,2147483647'],
        ];
    }
}
