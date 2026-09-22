<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssuedEntitlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'when' => $this->issued_at,
            'issued_by' => $this->whenLoaded('issuedBy', fn (): ?array => $this->issuedBy?->only('id', 'name')),
            'pass_name' => $this->whenLoaded(
                'expectedEntitlement',
                fn (): ?string => $this->expectedEntitlement?->passAssignment?->passType?->name,
            ),
        ];
    }
}
