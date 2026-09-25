<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_engagement_id', 'user_id', 'body'])]
class TeamEngagementNote extends Model
{
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(TeamEngagement::class, 'team_engagement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
