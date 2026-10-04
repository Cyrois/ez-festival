<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftAssignmentCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->person_name,
            'suggested' => (bool) $this->suggested, 'on_shift' => (bool) $this->on_shift,
            'role_name' => $this->role?->name,
            'group_name' => $this->group?->name,
            'overlaps' => $this->overlaps,
            'other_shifts' => $this->other_shifts ?? [],
        ];
    }
}
