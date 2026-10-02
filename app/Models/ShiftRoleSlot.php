<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['role_id', 'needed', 'sort_order'])]
class ShiftRoleSlot extends Model
{
    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'shift_role_slot_id');
    }

    protected function casts(): array
    {
        return [
            'needed' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function role(): BelongsTo
    {
        // #114 must add withTrashed() here when Role gains SoftDeletes.
        return $this->belongsTo(Role::class);
    }
}
