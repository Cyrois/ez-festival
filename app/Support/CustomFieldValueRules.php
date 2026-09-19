<?php

namespace App\Support;

use App\Models\CustomField;
use Illuminate\Validation\Rule;

class CustomFieldValueRules
{
    /**
     * @return array<int, mixed>
     */
    public static function for(CustomField $field): array
    {
        $presence = $field->required ? ['required'] : ['nullable'];

        return match ($field->type) {
            'textarea' => [...$presence, 'string', 'max:5000'],
            'number' => [...$presence, 'numeric'],
            'date' => [...$presence, 'date'],
            'select' => [...$presence, 'string', Rule::in($field->options ?? [])],
            'checkbox' => ['required', 'boolean'],
            default => [...$presence, 'string', 'max:255'],
        };
    }
}
