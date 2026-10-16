<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryReceiverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (int) $this->id, 'name' => $this->name,
            'is_manager' => $this->when(isset($this->resource->is_manager), fn () => (bool) $this->is_manager),
            'on_shift' => $this->when(isset($this->resource->on_shift), fn () => (bool) $this->on_shift),
            'in_group' => $this->when(isset($this->resource->in_group), fn () => (bool) $this->in_group),
        ];
    }
}
