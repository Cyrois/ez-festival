<?php

namespace App\Http\Requests\Credentials;

use App\Models\EntitlementItem;
use App\Services\EntitlementItemService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyEntitlementItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('credentials.entitlements.edit');
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $item = $this->route('entitlementItem');
            if ($item instanceof EntitlementItem && $item->event_id === $this->route('event')->id
                && $item->event_id === $this->user()->effectiveEvent()?->id
                && app(EntitlementItemService::class)->deletionBlocked($item)) {
                $validator->errors()->add('item', __('credentials.entitlements.errors.delete_blocked'));
            }
        });
    }

    public function rules(): array
    {
        return [];
    }
}
