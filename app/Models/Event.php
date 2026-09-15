<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'starts_on', 'ends_on', 'timezone', 'locked_at'])]
class Event extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'locked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function lock(): void
    {
        if ($this->locked_at === null) {
            $this->forceFill(['locked_at' => now()])->save();
        }
    }
}
