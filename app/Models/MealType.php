<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'starts_at', 'ends_at', 'sort_order'])]
class MealType extends Model
{
    use HasNormalizedName;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
