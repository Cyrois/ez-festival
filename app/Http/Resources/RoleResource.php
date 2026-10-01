<?php

namespace App\Http\Resources;

use App\Support\Permissions;
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
            'permissions' => Permissions::expand($this->permissions ?? []),
            'people_count' => (int) ($this->people_count ?? 0),
        ];
    }
}
