<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_at' => substr($this->starts_at, 0, 5),
            'ends_at' => substr($this->ends_at, 0, 5),
        ];
    }
}
