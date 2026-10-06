<?php

namespace App\Http\Resources;

use App\Services\ShiftAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftCopyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slots = $this->roleSlots->values();
        $indices = $slots->pluck('id')->flip();

        return [
            'name' => $this->name, 'color' => $this->color, 'location_id' => $this->location_id,
            'starts_at' => $this->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $this->ends_at->format('Y-m-d\TH:i'),
            'slots' => $slots->map(fn ($slot) => [
                'role_id' => $slot->role_id, 'role_name' => $slot->role->name, 'needed' => $slot->needed,
            ])->all(),
            'breaks' => $this->breaks->map(fn ($break) => [
                'duration_minutes' => $break->duration_minutes, 'starts_at' => $break->starts_at->format('Y-m-d\TH:i'),
            ])->all(),
            'assignments' => $this->assignments->map(fn ($assignment) => [
                'team_engagement_id' => $assignment->team_engagement_id,
                'name' => $assignment->teamEngagement->person->name,
                'role_id' => $assignment->role_id,
                'role_name' => $assignment->role->name,
                'slot_index' => $indices->get($assignment->shift_role_slot_id),
                'hours_mode' => $assignment->starts_at->eq($this->starts_at) && $assignment->ends_at->eq($this->ends_at) ? 'full_shift' : 'custom',
                'starts_at' => $assignment->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $assignment->ends_at->format('Y-m-d\TH:i'),
                'error' => ShiftAssignmentService::eligibilityError($assignment->teamEngagement),
                'overlaps' => $assignment->copy_overlaps,
                'other_shifts' => $assignment->copy_other_shifts,
            ])->all(),
        ];
    }
}
