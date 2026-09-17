<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'name' => $this->vendor->name,
            'status' => $this->status,
            'vendor_type_id' => $this->vendor_type_id,
            'type' => $this->vendorType?->name,
            'labels' => [],
            'custom' => [],
        ];
    }
}
