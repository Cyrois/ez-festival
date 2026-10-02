<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn () => $this->location->name),
            'starts_at' => $this->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $this->ends_at->format('Y-m-d\TH:i'),
            'slots' => $this->whenLoaded('roleSlots', fn () => ShiftRoleSlotResource::collection($this->roleSlots)->resolve($request)),
            'total_needs' => $this->whenLoaded('roleSlots', fn () => $this->roleSlots->sum('needed')),
            'filled_count' => 0,
        ];
    }
}
