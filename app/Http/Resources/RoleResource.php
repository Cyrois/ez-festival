<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'active' => $this->active,
            'can_read_team_notes' => $this->can_read_team_notes,
            'people_count' => (int) ($this->people_count ?? 0),
        ];
    }
}
