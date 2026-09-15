<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['organization_id', 'name', 'starts_on', 'ends_on', 'timezone', 'locked'])]
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
            'locked' => 'boolean',
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
        return (bool) $this->locked;
    }

    public function isPast(?Carbon $on = null): bool
    {
        $on ??= now();

        return $this->ends_on->lt($on->copy()->startOfDay());
    }

    public function lock(): void
    {
        if (! $this->locked) {
            $this->forceFill(['locked' => true])->save();
        }
    }

    public function unlock(): void
    {
        if ($this->locked) {
            $this->forceFill(['locked' => false])->save();
        }
    }
}
