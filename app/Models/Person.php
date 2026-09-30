<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'email', 'phone', 'can_log_in'])]
class Person extends Model
{
    public function scopeOnGlobalTeam(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('can_log_in', true)
            ->orWhereHas(
                'teamEngagements',
                fn (Builder $query) => $query->whereNotNull('role_id'),
            ));
    }

    protected function casts(): array
    {
        return [
            'can_log_in' => 'boolean',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

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

    public function eventPatrons(): HasMany
    {
        return $this->hasMany(EventPatron::class);
    }

    public function teamEngagements(): HasMany
    {
        return $this->hasMany(TeamEngagement::class);
    }
}
