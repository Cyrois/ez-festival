<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamEngagementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->person_id,
            'name' => $this->person->name,
            ...($request->user()->can('team.personal_info', $this->event) ? ['email' => $this->person->email, 'phone' => $this->person->phone, 'hourly_pay' => $this->hourly_pay] : ['personal_info_hidden' => true]),
            'status' => $this->status,
            'employment_type' => $this->employment_type,
            'group_id' => $this->group_id,
            'group' => $this->group?->only(['id', 'name']),
            'role_id' => $this->role_id,
            'role' => $this->role?->only(['id', 'name', 'active']),
            'pass_assignments' => $this->whenLoaded(
                'passAssignments',
                fn (): array => TeamPassAssignmentResource::collection($this->passAssignments)->resolve(),
            ),
        ];
    }
}
