<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_id', 'meal_id', 'team_engagement_id', 'meal_type_id', 'shift_meal_id', 'source_shift_id', 'meal_name', 'meal_date', 'starts_at', 'ends_at', 'shift_location_name', 'shift_starts_at', 'shift_ends_at', 'claimed_by', 'claimed_at', 'warning_overridden_by', 'warning_overridden_at'])]
class MealClaim extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['meal_date' => 'date', 'claimed_at' => 'datetime', 'warning_overridden_at' => 'datetime'];
    }
}
