<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'name', 'name_key', 'balance'])]
class EventStockItem extends Model
{
    use HasNormalizedName;

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(EventStockItemMovement::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(EventStockItemLabel::class, 'event_stock_item_label_assignments');
    }
}
