<?php

namespace App\Rules;

use App\Support\EventLocalTime;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EventLocalTimeRule implements ValidationRule
{
    public function __construct(private readonly string $timezone) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || EventLocalTime::instant($value, $this->timezone) === null) {
            $fail(__('team.scheduling.errors.local_time'));
        }
    }
}
