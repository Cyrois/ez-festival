<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shift_break_id', 'duration_minutes', 'starts_at', 'sort_order'])]
class ShiftAssignmentBreak extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'duration_minutes' => 'integer', 'sort_order' => 'integer'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'shift_assignment_id');
    }

    public function sourceBreak(): BelongsTo
    {
        return $this->belongsTo(ShiftBreak::class, 'shift_break_id');
    }
}
