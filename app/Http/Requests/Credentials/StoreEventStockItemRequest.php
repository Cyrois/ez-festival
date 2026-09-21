<?php

namespace App\Http\Requests\Credentials;

use App\Models\EventStockItemLabel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreEventStockItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-credentials');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'opening_balance' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'label_ids' => ['sometimes', 'array', 'max:50'],
            'label_ids.*' => ['integer', 'distinct', Rule::exists('event_stock_item_labels', 'id')],
            'new_labels' => ['sometimes', 'array', 'max:20'],
            'new_labels.*' => ['array:name,color'],
            'new_labels.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'new_labels.*.color' => ['required', Rule::in(EventStockItemLabel::COLORS)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $event = $this->route('event');
            $ids = $this->input('label_ids', []);

            if ($event !== null && is_array($ids) && EventStockItemLabel::query()
                ->whereIn('id', $ids)->where('event_id', '!=', $event->id)->exists()) {
                $validator->errors()->add('label_ids', __('credentials.entitlements.errors.label_unavailable'));
            }
        });
    }
}
