<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftMealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meal_id' => $this->meal_id,
            'meal' => (new MealResource($this->meal))->resolve($request),
            'assignment_ids' => $this->assignment_ids ?? [],
        ];
    }
}
