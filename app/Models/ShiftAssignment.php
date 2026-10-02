<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_engagement_id', 'shift_role_slot_id', 'role_id', 'starts_at', 'ends_at'])]
class ShiftAssignment extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function teamEngagement(): BelongsTo
    {
        return $this->belongsTo(TeamEngagement::class);
    }

    public function roleSlot(): BelongsTo
    {
        return $this->belongsTo(ShiftRoleSlot::class, 'shift_role_slot_id');
    }

    public function role(): BelongsTo
    {
        // Coordinate with #114: add withTrashed() when Role gains SoftDeletes.
        return $this->belongsTo(Role::class);
    }
}
