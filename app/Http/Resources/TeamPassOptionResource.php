<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamPassOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'full' => $this->max_assignments !== null && $this->assignments_count >= $this->max_assignments,
            'labels' => $this->labels
                ->map(fn ($label): array => $label->only('id', 'name', 'color'))
                ->values(),
            'entitlements' => $this->entitlements
                ->sortBy('sort_order')
                ->groupBy('entitlement_item_id')
                ->map(function ($lines): array {
                    $item = $lines->first()->entitlementItem;

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'quantity' => $lines->count(),
                        'labels' => $item->labels
                            ->map(fn ($label): array => $label->only('id', 'name', 'color'))
                            ->values(),
                    ];
                })
                ->values(),
        ];
    }
}
