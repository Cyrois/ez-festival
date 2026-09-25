<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'starts_on', 'ends_on', 'timezone'])]
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

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function isLocked(): bool
    {
        return (bool) $this->locked;
    }

    public function artistEngagements(): HasMany
    {
        return $this->hasMany(ArtistEngagement::class);
    }

    public function vendorEngagements(): HasMany
    {
        return $this->hasMany(VendorEngagement::class);
    }

    public function passTypes(): HasMany
    {
        return $this->hasMany(PassType::class);
    }

    public function entitlementItems(): HasMany
    {
        return $this->hasMany(EntitlementItem::class);
    }

    public function patrons(): HasMany
    {
        return $this->hasMany(EventPatron::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function teamEngagements(): HasMany
    {
        return $this->hasMany(TeamEngagement::class);
    }

    public function teamForms(): HasMany
    {
        return $this->hasMany(TeamForm::class);
    }

    public function entitlementItemLabels(): HasMany
    {
        return $this->hasMany(EntitlementItemLabel::class);
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

    /**
     * Abort if this event is locked.
     * Primary is only the user's default open event — it does not gate writes.
     * Lock/unlock actions must not call this.
     */
    public function ensureWritable(): void
    {
        if ($this->isLocked()) {
            abort(403, 'This event is locked and read-only.');
        }
    }
}
