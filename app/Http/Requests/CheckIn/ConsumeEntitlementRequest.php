<?php

namespace App\Http\Requests\CheckIn;

use App\Support\EntitlementCode;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConsumeEntitlementRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        if (is_string($code)) {
            $code = trim($code);
        }
        $this->merge(['code' => $code === '' ? null : $code]);
    }

    public function authorize(): bool
    {
        return Gate::allows('checkin.edit');
    }

    public function rules(): array
    {
        $eventId = $this->user()?->effectiveEvent()?->id;
        $item = $this->route('expectedEntitlement')?->entitlementItem;
        $needsUniqueCode = $item?->event_id === $eventId && $item?->needs_unique_code;

        return [
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('event_id', $eventId),
            ],
            'code' => [
                'bail', Rule::requiredIf($needsUniqueCode), 'nullable', 'string', 'max:64',
                function (string $attribute, mixed $value, Closure $fail) use ($needsUniqueCode, $eventId): void {
                    if ($needsUniqueCode && EntitlementCode::isUsed($eventId, $value)) {
                        $fail(__('check_in.errors.code_used'));
                    }
                },
            ],
        ];
    }
}
