<?php

namespace App\Http\Requests\Navigation;

use App\Support\ReturnNavigation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ReturnNavigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'return_to' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! ReturnNavigation::valid($value)) {
                    $fail(__('navigation.invalid_return'));
                }
            }],
        ];
    }
}
