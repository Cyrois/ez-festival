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
            'max_assignments' => $this->max_assignments,
            'assignments_count' => (int) ($this->assignments_count ?? 0),
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
