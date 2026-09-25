<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'name', 'status', 'public_token'])]
class TeamForm extends Model
{
    public const STATUSES = ['draft', 'live'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(TeamFormField::class)->orderBy('sort_order')->orderBy('id');
    }

    public function teamEngagements(): HasMany
    {
        return $this->hasMany(TeamEngagement::class);
    }
}
