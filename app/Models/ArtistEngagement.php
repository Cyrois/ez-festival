<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['artist_id', 'event_id', 'artist_type_id', 'status'])]
class ArtistEngagement extends Model
{
    use HasFactory;

    public const STATUSES = ['idea', 'outreach', 'negotiating', 'contract_sent', 'confirmed', 'declined'];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function artistType(): BelongsTo
    {
        return $this->belongsTo(ArtistType::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ArtistEngagementNote::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ArtistLabel::class, 'artist_engagement_label_assignments');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'artist_engagement_people')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function passAssignments(): HasMany
    {
        return $this->hasMany(PassAssignment::class);
    }
}
