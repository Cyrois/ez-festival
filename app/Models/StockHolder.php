<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'location_id', 'person_id'])]
class StockHolder extends Model
{
    public $timestamps = false;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function hasHistory(): bool
    {
        return StockMovement::query()->where('event_id', $this->event_id)
            ->where(fn ($query) => $query->where('from_holder_id', $this->id)->orWhere('to_holder_id', $this->id))
            ->exists();
    }
}
