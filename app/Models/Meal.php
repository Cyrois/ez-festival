<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['meal_type_id', 'name', 'date', 'starts_at', 'ends_at'])]
class Meal extends Model
{
    use HasNormalizedName;

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function shiftMeals(): HasMany
    {
        return $this->hasMany(ShiftMeal::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function mealType(): BelongsTo
    {
        return $this->belongsTo(MealType::class);
    }
}
