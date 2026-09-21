<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'event_stock_item_id',
    'kind',
    'quantity_delta',
    'balance_after',
    'reason',
    'created_by_user_id',
    'issued_to_person_id',
    'credential_code',
])]
class EventStockItemMovement extends Model
{
    public const KIND_OPENING = 'opening';

    public const KIND_ADJUSTMENT = 'adjustment';

    public const KIND_CHECK_IN = 'check_in';

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(EventStockItem::class, 'event_stock_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function issuedTo(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'issued_to_person_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function contextable(): MorphTo
    {
        return $this->morphTo();
    }
}
