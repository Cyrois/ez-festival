<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckInPersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return collect($this->resource)->except('searchable')->all();
    }
}
