<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntitlementItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'balance' => (int) ($this->balance ?? 0),
            'labels' => $this->whenLoaded('labels', fn (): array => $this->labels
                ->map(fn ($label): array => $label->only('id', 'name', 'color'))
                ->values()
                ->all()),
        ];
    }
}
