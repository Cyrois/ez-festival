<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryHistoryOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['value' => (string) $this->value, 'name' => $this->name, 'kind' => $this->kind];
    }
}
