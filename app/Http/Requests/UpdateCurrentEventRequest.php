<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrentEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessEvent($this->route('event')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
