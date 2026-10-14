<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'entitlement_item_id', 'quantity', 'from_holder_id', 'to_holder_id', 'type', 'actor_user_id', 'note', 'issued_entitlement_id'])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    public const ADD = 'add';

    public const REMOVE = 'remove';

    public const PULL = 'pull';

    public const RETURN = 'return';

    public const TRANSFER = 'transfer';

    public const TAKE_BACK = 'take_back';

    public const GIVE_OUT = 'give_out';

    public const TYPES = [self::ADD, self::REMOVE, self::PULL, self::RETURN, self::TRANSFER, self::TAKE_BACK, self::GIVE_OUT];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
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
