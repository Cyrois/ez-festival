<?php

namespace App\Models;

use App\Enums\StockMovement as StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'entitlement_item_id', 'quantity', 'from_holder_id', 'to_holder_id', 'type', 'actor_user_id', 'note', 'issued_entitlement_id'])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'type' => StockMovementType::class];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(EntitlementItem::class, 'entitlement_item_id');
    }

    public function fromHolder(): BelongsTo
    {
        return $this->belongsTo(StockHolder::class, 'from_holder_id');
    }

    public function toHolder(): BelongsTo
    {
        return $this->belongsTo(StockHolder::class, 'to_holder_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function issuedEntitlement(): BelongsTo
    {
        return $this->belongsTo(IssuedEntitlement::class);
    }
}
