<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['pass_assignment_id', 'entitlement_item_id', 'status'])]
class ExpectedEntitlement extends Model
{
    public const STATUS_EXPECTED = 'expected';

    public const STATUS_CONSUMED = 'consumed';

    public function passAssignment(): BelongsTo
    {
        return $this->belongsTo(PassAssignment::class);
    }

    public function entitlementItem(): BelongsTo
    {
        return $this->belongsTo(EntitlementItem::class);
    }

    public function issuedEntitlement(): HasOne
    {
        return $this->hasOne(IssuedEntitlement::class);
    }
}
