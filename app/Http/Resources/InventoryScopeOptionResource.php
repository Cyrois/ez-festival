<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryScopeOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (int) $this->id, 'name' => $this->name,
            'location' => $this->when(isset($this->resource->location), fn () => $this->location),
            'starts_at' => $this->when(isset($this->resource->starts_at), fn () => $this->starts_at),
            'ends_at' => $this->when(isset($this->resource->ends_at), fn () => $this->ends_at),
            'period' => $this->when(isset($this->resource->period), fn () => (int) $this->period),
        ];
    }
}
