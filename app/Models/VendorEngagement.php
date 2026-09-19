<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['vendor_id', 'event_id', 'vendor_type_id', 'status'])]
class VendorEngagement extends Model
{
    use HasFactory;

    public const STATUSES = ['idea', 'outreach', 'negotiating', 'contract_sent', 'confirmed', 'declined'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function vendorType(): BelongsTo
    {
        return $this->belongsTo(VendorType::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(VendorEngagementNote::class);
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'vendor_engagement_people')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function passAssignments(): MorphMany
    {
        return $this->morphMany(PassAssignment::class, 'assignable');
    }
}
