<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->teamEngagement->person->name,
            'role_id' => $this->role_id, 'role_name' => $this->role->name,
            'shift_role_slot_id' => $this->shift_role_slot_id,
            'starts_at' => $this->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $this->ends_at->format('Y-m-d\TH:i'),
            'breaks' => $this->whenLoaded('breaks', fn () => ShiftAssignmentBreakResource::collection($this->breaks)->resolve($request)),
            'scheduled_minutes' => (int) $this->scheduled_minutes,
            'is_extra' => (bool) $this->is_extra, 'overlaps' => $this->overlaps,
            'other_shifts' => $this->other_shifts ?? [],
        ];
    }
}
