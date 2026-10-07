<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assigned_to_shifts' => $this->whenHas('shift_meals_exists', fn () => (bool) $this->shift_meals_exists),
            'name' => $this->name,
            'meal_type_id' => $this->meal_type_id,
            'meal_type' => $this->whenLoaded('mealType', fn () => (new MealTypeResource($this->mealType))->resolve($request)),
            'date' => $this->date->format('Y-m-d'),
            'starts_at' => substr($this->starts_at, 0, 5),
            'ends_at' => substr($this->ends_at, 0, 5),
        ];
    }
}
