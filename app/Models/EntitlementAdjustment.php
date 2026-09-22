<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'entitlement_item_id',
    'location_id',
    'delta',
    'reason',
    'user_id',
])]
class EntitlementAdjustment extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(EntitlementItem::class, 'entitlement_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
