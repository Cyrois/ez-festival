<?php

namespace App\Http\Resources;

use App\Support\EntitlementLabels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntitlementItemOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'low_stock_level' => $this->low_stock_level,
            'labels' => EntitlementLabels::ordered($this->labels),
        ];
    }
}
