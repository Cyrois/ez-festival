<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Artist extends Model
{
    use HasFactory, HasNormalizedName;

    public function engagements(): HasMany
    {
        return $this->hasMany(ArtistEngagement::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ArtistLabel::class, 'artist_label_assignments');
    }
}
