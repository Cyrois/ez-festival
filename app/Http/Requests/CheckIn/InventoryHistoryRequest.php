<?php

namespace App\Http\Requests\CheckIn;

use App\Enums\StockMovement;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Queries\InventoryHistoryQuery;
use App\Support\EventContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InventoryHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows($this->route('entitlementItem') instanceof EntitlementItem ? 'view-entitlements' : 'inventory.manage');
    }

    public function event(): Event
    {
        return app(EventContext::class)->requireCurrent($this->user());
    }

    public function item(): ?EntitlementItem
    {
        $item = $this->route('entitlementItem');
        if (! $item instanceof EntitlementItem) {
            return null;
        }
        abort_unless($item->event_id === $this->event()->id, 404);

        return $item;
    }

    public function rules(): array
    {
        $item = $this->item();

        return [
            'draw' => ['sometimes', 'integer', 'min:0'],
            'start' => ['sometimes', 'integer', 'min:0'],
            'length' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
            'item_id' => ['nullable', 'integer', Rule::exists('entitlement_items', 'id')->where('event_id', $this->event()->id), ...($item ? [Rule::in([$item->id])] : [])],
            'holder' => ['nullable', 'string', 'max:40', 'regex:/\A(person|location):[1-9][0-9]*\z/'],
            'recorded_by' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(StockMovement::class)],
            'start_date' => ['nullable', 'required_with:end_date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'required_with:start_date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'option' => ['sometimes', Rule::in(['holder', 'actor'])],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $query = app(InventoryHistoryQuery::class);
            $itemId = $this->item()?->id;
            if ($this->filled('holder') && ! $query->holders($this->event(), $itemId)->where('value', $this->input('holder'))->exists()) {
                $validator->errors()->add('holder', __('validation.exists', ['attribute' => __('inventory.history.holder')]));
            }
            if ($this->filled('recorded_by') && ! $query->actors($this->event(), $itemId)->where('value', (string) $this->input('recorded_by'))->exists()) {
                $validator->errors()->add('recorded_by', __('validation.exists', ['attribute' => __('inventory.columns.recorded_by')]));
            }
        });
    }
}
