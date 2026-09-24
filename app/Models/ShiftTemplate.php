<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'location_id', 'name'])]
class ShiftTemplate extends Model
{
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function roleLines(): HasMany
    {
        return $this->hasMany(ShiftTemplateRole::class)->orderBy('id');
    }

    public function needs(): int
    {
        if ($this->relationLoaded('roleLines')) {
            return (int) $this->roleLines->sum('headcount');
        }

        return (int) $this->roleLines()->sum('headcount');
    }
}
