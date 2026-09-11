<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'active_event_id', 'setup_completed_at'])]
class Organization extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'setup_completed_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function activeEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'active_event_id');
    }

    public function vendorTypes(): HasMany
    {
        return $this->hasMany(VendorType::class);
    }

    public function artistTypes(): HasMany
    {
        return $this->hasMany(ArtistType::class);
    }

    public function setupIsComplete(): bool
    {
        return $this->setup_completed_at !== null;
    }

    public function markSetupComplete(): void
    {
        if ($this->setup_completed_at === null) {
            $this->forceFill(['setup_completed_at' => now()])->save();
        }
    }
}
