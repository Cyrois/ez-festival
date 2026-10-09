<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'meal_id', 'team_engagement_id', 'meal_type_id', 'shift_meal_id', 'shift_assignment_id', 'source_shift_id', 'is_active', 'is_override', 'override_given_by', 'override_given_at', 'meal_name', 'meal_date', 'starts_at', 'ends_at', 'shift_location_name', 'shift_starts_at', 'shift_ends_at', 'claimed_by', 'claimed_at', 'claim_token', 'warning_overridden_by', 'warning_overridden_at'])]
class MealAssignment extends Model
{
    public $timestamps = false;

    public function teamEngagement(): BelongsTo
    {
        return $this->belongsTo(TeamEngagement::class);
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    public function mealType(): BelongsTo
    {
        return $this->belongsTo(MealType::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_override' => 'boolean', 'override_given_at' => 'datetime', 'meal_date' => 'date', 'claimed_at' => 'datetime', 'warning_overridden_at' => 'datetime'];
    }
}
