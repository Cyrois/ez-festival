<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomFieldValue extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:6',
            'value_date' => 'date:Y-m-d',
            'value_boolean' => 'boolean',
        ];
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function customFieldable(): MorphTo
    {
        return $this->morphTo();
    }

    public function setTypedValue(CustomField $field, mixed $value): void
    {
        $this->value_text = null;
        $this->value_search = null;
        $this->value_number = null;
        $this->value_date = null;
        $this->value_boolean = null;

        match ($field->type) {
            'number' => $this->value_number = $value,
            'date' => $this->value_date = $value,
            'checkbox' => $this->value_boolean = (bool) $value,
            default => $this->setTextValue((string) $value),
        };
    }

    public function typedValue(CustomField $field): mixed
    {
        return match ($field->type) {
            'number' => $this->value_number,
            'date' => $this->value_date?->format('Y-m-d'),
            'checkbox' => $this->value_boolean,
            default => $this->value_text,
        };
    }

    private function setTextValue(string $value): void
    {
        $this->value_text = $value;
        $this->value_search = mb_substr(mb_strtolower($value), 0, 255);
    }
}
