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
            'filled_count' => $this->whenLoaded('roleSlots', fn () => $this->roleSlots->sum(fn ($slot) => min((int) $slot->assigned_count, $slot->needed))),
            'assignment_count' => (int) ($this->assignments_count ?? 0),
            'extra_count' => $this->whenLoaded('roleSlots', fn () => max((int) ($this->assignments_count ?? 0) - $this->roleSlots->sum(fn ($slot) => min((int) $slot->assigned_count, $slot->needed)), 0)),
            'assignments' => $this->whenLoaded('assignments', fn () => ShiftAssignmentResource::collection($this->assignments)->resolve($request)),
        ];
    }
}
