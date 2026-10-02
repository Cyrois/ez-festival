<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftRoleSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role_id' => $this->role_id,
            'role_name' => $this->whenLoaded('role', fn () => $this->role->name),
            'needed' => $this->needed,
            'sort_order' => $this->sort_order,
            'assigned_count' => (int) ($this->assigned_count ?? 0),
            'filled_count' => min((int) ($this->assigned_count ?? 0), $this->needed),
            'open_count' => max($this->needed - (int) ($this->assigned_count ?? 0), 0),
            'extra_count' => max((int) ($this->assigned_count ?? 0) - $this->needed, 0),
        ];
    }
}
