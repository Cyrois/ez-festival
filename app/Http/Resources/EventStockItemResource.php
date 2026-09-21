<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventStockItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'balance' => $this->balance,
            'labels' => $this->whenLoaded('labels', fn (): array => $this->labels
                ->map(fn ($label): array => $label->only('id', 'name', 'color'))
                ->values()
                ->all()),
        ];
    }
}
