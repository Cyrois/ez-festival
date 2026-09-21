<?php

namespace App\Http\Resources;

use App\Models\ArtistEngagement;
use App\Models\VendorEngagement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventStockItemMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'when' => $this->created_at?->toISOString(),
            'item_name' => $this->stockItem?->name,
            'quantity' => abs($this->quantity_delta),
            'issued_to' => $this->issuedTo?->name,
            'context' => $this->context(),
            'code' => $this->credential_code,
        ];
    }

    /**
     * @return array{type: string, name: string}|null
     */
    private function context(): ?array
    {
        $context = $this->contextable;

        if ($context instanceof ArtistEngagement) {
            return [
                'type' => 'artist',
                'name' => $context->artist?->name ?? '',
            ];
        }

        if ($context instanceof VendorEngagement) {
            return [
                'type' => 'vendor',
                'name' => $context->vendor?->name ?? '',
            ];
        }

        return null;
    }
}
