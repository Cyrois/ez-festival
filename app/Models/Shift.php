<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'location_id', 'name', 'starts_at', 'ends_at'])]
class Shift extends Model
{
    /**
     * @return array<string, string>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class)->orderBy('shift_assignments.created_at')->orderBy('shift_assignments.id');
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function roleSlots(): HasMany
    {
        return $this->hasMany(ShiftRoleSlot::class)
            ->withCount(['assignments as assigned_count' => fn ($query) => $query->whereColumn('shift_assignments.role_id', 'shift_role_slots.role_id')])
            ->orderBy('sort_order')->orderBy('id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
