<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use App\Services\EntitlementItemService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['event_id', 'name', 'name_key', 'color'])]
class EntitlementItemLabel extends Model
{
    use HasNormalizedName;

    protected static function booted(): void
    {
        $invalidate = fn (self $label) => app(EntitlementItemService::class)->forgetList($label->event_id);
        static::saved($invalidate);
        static::deleted($invalidate);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(EntitlementItem::class, 'entitlement_item_label_assignments');
    }
}
