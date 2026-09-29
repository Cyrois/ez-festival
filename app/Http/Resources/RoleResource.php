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
            // Roles are not given to Team records yet, so no one holds a role. Once they are, this
            // becomes a count of distinct people holding the role in at least one event.
            'people_count' => (int) ($this->people_count ?? 0),
        ];
    }
}
