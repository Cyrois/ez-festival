<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'when' => $this->created_at->toIso8601String(),
            'item' => $this->item->name, 'quantity' => $this->quantity, 'type' => $this->type->value,
            'giver' => $this->fromHolder?->person?->name ?? $this->fromHolder?->location?->name,
            'receiver' => $this->toHolder?->person?->name ?? $this->toHolder?->location?->name
                ?? $this->issuedEntitlement?->expectedEntitlement?->passAssignment?->person?->name,
            'recorded_by' => $this->actor?->name,
        ];
    }
}
