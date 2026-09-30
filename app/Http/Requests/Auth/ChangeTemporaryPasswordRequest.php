<?php

namespace App\Http\Requests\Auth;

use App\Support\TeamPassword;
use Illuminate\Foundation\Http\FormRequest;

class ChangeTemporaryPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->must_change_password === true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', TeamPassword::rule()],
        ];
    }
}
