<?php

namespace App\Http\Requests\CheckIn;

use App\Queries\InventoryTargetsQuery;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TakeBackStockRequest extends StockMovementRequest
{
    public function authorize(): bool
    {
        return Gate::allows('inventory.manage');
    }

    public function rules(): array
    {
        return [...parent::rules(),
            'destination' => ['required', Rule::in(['me', 'location', 'person'])],
            'location_id' => ['nullable', 'required_if:destination,location', 'prohibited_unless:destination,location', 'integer', Rule::exists('locations', 'id')->where('event_id', $this->route('event')->id)],
            'person_id' => ['nullable', 'required_if:destination,person', 'prohibited_unless:destination,person', 'integer', 'exists:people,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->isEmpty() || $this->input('destination') === 'location') {
                return;
            }
            $personId = $this->input('destination') === 'me' ? $this->user()->person_id : $this->integer('person_id');
            if ($personId === $this->route('holder')->person_id
                || ! app(InventoryTargetsQuery::class)->canReceive($this->route('event'), $personId)) {
                $validator->errors()->add('person_id', __('inventory.errors.receiver_unavailable'));
            }
        }];
    }
}
