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
            'people' => $this->relationLoaded('people')
                ? PersonResource::collection($this->people)->resolve()
                : [],
            'pass_assignments' => $this->relationLoaded('passAssignments')
                ? PassAssignmentResource::collection($this->passAssignments)->resolve()
                : [],
            'custom' => $this->vendor->relationLoaded('customFieldValues')
                ? $this->vendor->customFieldValues->mapWithKeys(fn ($value) => [$value->custom_field_id => $value->value])->all()
                : [],
        ];
    }
}
