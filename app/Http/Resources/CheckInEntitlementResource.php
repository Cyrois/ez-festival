<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckInEntitlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $issued = $this->issuedEntitlement;

        return [
            'id' => $this->id,
            'name' => $this->entitlementItem->name,
            'source' => $this->passAssignment->passType->name,
            'status' => $issued ? 'issued' : 'pending',
            'locations' => $this->stock_locations->map(fn ($stock): array => [
                'id' => $stock->location_id, 'name' => $stock->name, 'in_stock' => (int) $stock->balance,
            ])->values(),
            'issued' => $issued ? [
                'location' => $issued->location?->name,
                'code' => $issued->code,
                'issued_by' => $issued->issuedBy?->name,
                'issued_at' => $issued->issued_at,
            ] : null,
        ];
    }
}
