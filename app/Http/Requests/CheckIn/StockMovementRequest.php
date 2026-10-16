<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('checkin.edit');
    }

    public function rules(): array
    {
        return [
            'entitlement_item_id' => ['required', 'integer', Rule::exists('entitlement_items', 'id')->where('event_id', $this->route('event')->id)],
            'quantity' => ['required', 'integer', 'between:1,2147483647'],
        ];
    }
}
