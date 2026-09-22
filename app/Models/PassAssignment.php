<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['pass_type_id', 'artist_engagement_id', 'vendor_engagement_id', 'event_patron_id', 'person_id'])]
class PassAssignment extends Model
{
    public function passType(): BelongsTo
    {
        return $this->belongsTo(PassType::class);
    }

    public function artistEngagement(): BelongsTo
    {
        return $this->belongsTo(ArtistEngagement::class);
    }

    public function vendorEngagement(): BelongsTo
    {
        return $this->belongsTo(VendorEngagement::class);
    }

    public function eventPatron(): BelongsTo
    {
        return $this->belongsTo(EventPatron::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function expectedEntitlements(): HasMany
    {
        return $this->hasMany(ExpectedEntitlement::class);
    }
}
