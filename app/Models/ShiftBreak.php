<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['duration_minutes', 'starts_at', 'sort_order'])]
class ShiftBreak extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'duration_minutes' => 'integer', 'sort_order' => 'integer'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
