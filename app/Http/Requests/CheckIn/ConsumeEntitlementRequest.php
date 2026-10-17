<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

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
        return [
            'location_id' => ['prohibited'],
            'holder_id' => ['prohibited'],
            'code' => ['nullable', 'string', 'max:255'],
        ];
    }
}
