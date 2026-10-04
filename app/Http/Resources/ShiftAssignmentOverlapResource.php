<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftAssignmentOverlapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_intersect_key($this->resource, array_flip([
            'shift_id', 'shift_name', 'starts_at', 'ends_at', 'overlap_minutes',
        ]));
    }
}
