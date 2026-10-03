<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Reuse the roster/List count contract without disclosing slot or person data.
        return array_intersect_key((new ShiftResource($this->resource))->resolve($request), array_flip([
            'id', 'name', 'color', 'location_id', 'starts_at', 'ends_at',
            'total_needs', 'filled_count', 'assignment_count',
        ]));
    }
}
