<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['event_id', 'name', 'max_assignments'])]
class PassType extends Model
{
    protected function casts(): array
    {
        return [
            'max_assignments' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(
            PassTypeLabel::class,
            'pass_type_label_assignments',
        );
    }

    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'custom_fieldable');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PassAssignment::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(PassTypeEntitlement::class);
    }
}
