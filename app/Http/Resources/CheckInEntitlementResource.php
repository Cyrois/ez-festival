<?php

namespace App\Http\Resources;

use App\Support\EntitlementLabels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckInEntitlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $issued = $this->issuedEntitlement;
        $movement = $issued?->giveOutMovement;

        return [
            'id' => $this->id,
            'name' => $this->entitlementItem->name,
            'labels' => EntitlementLabels::ordered($this->entitlementItem->labels),
            'source' => $this->passAssignment->passType->name,
            'status' => $issued ? 'issued' : 'pending',
            'own_stock' => $this->own_stock,
            'can_consume' => $this->can_consume,
            'consume_blocked_reason' => $this->consume_blocked_reason,
            'issued' => $issued ? [
                'giver' => $movement?->fromHolder?->person?->name ?? $movement?->fromHolder?->location?->name,
                'receiver' => $this->passAssignment->person?->name,
                'code' => $issued->code,
                'issued_by' => $issued->issuedBy?->name,
                'recorded_by' => $movement?->actor?->name,
                'issued_at' => $issued->issued_at,
            ] : null,
        ];
    }
}
