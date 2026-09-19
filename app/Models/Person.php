<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'phone'])]
class Person extends Model
{
    public function artistEngagements(): BelongsToMany
    {
        return $this->belongsToMany(ArtistEngagement::class, 'artist_engagement_people')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function vendorEngagements(): BelongsToMany
    {
        return $this->belongsToMany(VendorEngagement::class, 'vendor_engagement_people')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function passAssignments(): HasMany
    {
        return $this->hasMany(PassAssignment::class);
    }
}
