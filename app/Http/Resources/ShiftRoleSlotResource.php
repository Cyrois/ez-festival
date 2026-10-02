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
            'is_supervisor' => $this->is_supervisor,
            'sort_order' => $this->sort_order,
        ];
    }
}
