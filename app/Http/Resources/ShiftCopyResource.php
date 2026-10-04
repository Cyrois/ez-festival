<?php

namespace App\Http\Resources;

use App\Models\Shift;
use App\Support\ShiftAssignmentOverlaps;
use App\Support\ShiftCopyAssignments;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftCopyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slots = $this->roleSlots->values();
        $indices = $slots->pluck('id')->flip();
        $draft = new Shift($this->resource->only('event_id', 'starts_at', 'ends_at'));
        $others = ShiftAssignmentOverlaps::forMembers($draft, $this->assignments->pluck('team_engagement_id')->all(), $this->starts_at, $this->ends_at);

        return [
            'copy' => $this->id,
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
                'role_name' => $assignment->role->name,
                'slot_index' => $indices->get($assignment->shift_role_slot_id),
                'hours_mode' => $assignment->starts_at->eq($this->starts_at) && $assignment->ends_at->eq($this->ends_at) ? 'full_shift' : 'custom',
                'starts_at' => $assignment->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $assignment->ends_at->format('Y-m-d\TH:i'),
                'error' => ShiftCopyAssignments::eligibilityError($assignment->teamEngagement),
                'overlaps' => ShiftAssignmentOverlaps::warnings($others->get($assignment->team_engagement_id, collect()), $assignment->starts_at, $assignment->ends_at, $this->event->timezone),
            ])->all(),
        ];
    }
}
