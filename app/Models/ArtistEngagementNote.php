<?php

namespace App\Models;

use Database\Factories\ArtistEngagementNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['artist_engagement_id', 'user_id', 'body'])]
class ArtistEngagementNote extends Model
{
    /** @use HasFactory<ArtistEngagementNoteFactory> */
    use HasFactory;

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(ArtistEngagement::class, 'artist_engagement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
