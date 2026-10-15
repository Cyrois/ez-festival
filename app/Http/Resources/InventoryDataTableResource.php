<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryDataTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['draw' => $this->resource['draw'], 'recordsTotal' => $this->resource['total'],
            'recordsFiltered' => $this->resource['filtered'], 'data' => $this->resource['rows']];
    }
}
