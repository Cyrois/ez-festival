<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealOverrideOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (int) $this->id, 'name' => $this->name, 'date' => $this->meal_date, 'type' => $this->type_name,
            'starts_at' => substr($this->starts_at, 0, 5), 'ends_at' => substr($this->ends_at, 0, 5),
            'available' => (bool) $this->available, 'extra' => (bool) $this->extra];
    }
}
