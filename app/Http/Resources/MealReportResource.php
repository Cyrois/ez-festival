<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['rows' => $this->resource->values()->all()];
    }
}
