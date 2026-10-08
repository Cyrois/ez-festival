<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['meal_id'])]
class ShiftMeal extends Model
{
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(ShiftAssignment::class, 'meal_assignments')->wherePivot('is_active', true);
    }
}
