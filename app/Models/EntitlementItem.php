<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'name'])]
class EntitlementItem extends Model
{
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(EntitlementAdjustment::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ArtistLabel::class, 'entitlement_item_label_assignments');
    }

    public function passTypeEntitlements(): HasMany
    {
        return $this->hasMany(PassTypeEntitlement::class);
    }

    public function expectedEntitlements(): HasMany
    {
        return $this->hasMany(ExpectedEntitlement::class);
    }

    public function issuedEntitlements(): HasMany
    {
        return $this->hasMany(IssuedEntitlement::class);
    }
}
