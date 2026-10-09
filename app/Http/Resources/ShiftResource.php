<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewTeam = $this->relationLoaded('assignments') && Gate::allows('team.view');
        $supervisor = $this->relationLoaded('assignments')
            ? $this->assignments->firstWhere('is_supervisor', true)
            : ($this->relationLoaded('supervisorAssignment') ? $this->supervisorAssignment : null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'supervisor_name' => $supervisor?->teamEngagement->person->name,
            'color' => $this->color,
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn () => $this->location->name),
            'starts_at' => $this->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $this->ends_at->format('Y-m-d\TH:i'),
            'meals' => $this->whenLoaded('meals', fn () => ShiftMealResource::collection($this->meals)->resolve($request)),
            'breaks' => $this->whenLoaded('breaks', fn () => ShiftBreakResource::collection($this->breaks)->resolve($request)),
            'slots' => $this->whenLoaded('roleSlots', fn () => ShiftRoleSlotResource::collection($this->roleSlots)->resolve($request)),
            'total_needs' => $this->whenLoaded('roleSlots', fn () => $this->roleSlots->sum('needed')),
            'filled_count' => $this->whenLoaded('roleSlots', fn () => $this->roleSlots->sum(fn ($slot) => min((int) $slot->assigned_count, $slot->needed))),
            'assignment_count' => (int) ($this->assignments_count ?? 0),
            'extra_count' => $this->whenLoaded('roleSlots', fn () => max((int) ($this->assignments_count ?? 0) - $this->roleSlots->sum(fn ($slot) => min((int) $slot->assigned_count, $slot->needed)), 0)),
            'assignments' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($assignment) => [
                ...(new ShiftAssignmentResource($assignment))->resolve($request),
                'member_url' => $canViewTeam ? route('team.members.show', $assignment->team_engagement_id) : null,
            ])->all()),
        ];
    }
}
