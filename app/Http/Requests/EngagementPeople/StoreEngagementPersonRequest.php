<?php

namespace App\Http\Requests\EngagementPeople;

use Illuminate\Foundation\Http\FormRequest;

class StoreEngagementPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ];
    }
}
