<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'name', 'needs_unique_code', 'low_stock_level'])]
class EntitlementItem extends Model
{
    protected function casts(): array
    {
        return ['needs_unique_code' => 'boolean', 'low_stock_level' => 'integer'];
    }

    public function scopeWithEventLabels(Builder $query, int $eventId): void
    {
        $query->with(['labels' => fn ($labels) => $labels
            ->where('event_id', $eventId)
            ->select('entitlement_item_labels.id', 'name', 'color')]);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(EntitlementItemLabel::class, 'entitlement_item_label_assignments');
    }

    public function passTypeEntitlements(): HasMany
    {
        return $this->hasMany(PassTypeEntitlement::class);
    }

    public function expectedEntitlements(): HasMany
    {
        return $this->hasMany(ExpectedEntitlement::class);
    }

    public function issuedEntitlements(): HasMany
    {
        return $this->hasMany(IssuedEntitlement::class);
    }
}
