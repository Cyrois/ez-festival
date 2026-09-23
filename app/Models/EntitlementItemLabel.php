<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['event_id', 'name', 'name_key', 'color'])]
class EntitlementItemLabel extends Model
{
    use HasNormalizedName;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(EntitlementItem::class, 'entitlement_item_label_assignments');
    }
}
