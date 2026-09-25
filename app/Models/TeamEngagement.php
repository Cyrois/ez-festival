<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'person_id', 'group_id', 'status', 'employment_type', 'hourly_pay'])]
class TeamEngagement extends Model
{
    public const STATUSES = ['applied', 'reviewing', 'hired', 'declined'];

    public const EMPLOYMENT_TYPES = ['volunteer', 'paid'];

    protected function casts(): array
    {
        return [
            'hourly_pay' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TeamEngagementNote::class);
    }
}
