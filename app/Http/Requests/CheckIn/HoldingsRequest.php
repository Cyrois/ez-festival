<?php

namespace App\Http\Requests\CheckIn;

use App\Services\InventoryHoldingsAccess;
use Illuminate\Validation\Rule;

class HoldingsRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event') ?? $this->user()->effectiveEvent();

        return $event !== null && app(InventoryHoldingsAccess::class)->canView($event, $this->user());
    }

    public function rules(): array
    {
        $event = $this->route('event') ?? $this->user()->effectiveEvent();

        return [...parent::rules(),
            'shift_id' => ['nullable', 'required_without:group_id', 'prohibits:group_id', 'integer', Rule::exists('shifts', 'id')->where('event_id', $event?->id)],
            'group_id' => ['nullable', 'required_without:shift_id', 'prohibits:shift_id', 'integer', Rule::exists('groups', 'id')->where('event_id', $event?->id)],
        ];
    }
}
