<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pass_type_id', 'entitlement_item_id', 'sort_order'])]
class PassTypeEntitlement extends Model
{
    public function passType(): BelongsTo
    {
        return $this->belongsTo(PassType::class);
    }

    public function entitlementItem(): BelongsTo
    {
        return $this->belongsTo(EntitlementItem::class);
    }
}
