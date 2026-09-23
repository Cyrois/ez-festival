<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['expected_entitlement_id', 'entitlement_item_id', 'location_id', 'code', 'issued_by', 'issued_at'])]
class IssuedEntitlement extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    public function expectedEntitlement(): BelongsTo
    {
        return $this->belongsTo(ExpectedEntitlement::class);
    }

    public function entitlementItem(): BelongsTo
    {
        return $this->belongsTo(EntitlementItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
