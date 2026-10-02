<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class VendorCheckInShowResource extends CheckInShowResource
{
    public function toArray(Request $request): array
    {
        return $this->engagementData($request, 'vendors.personal_info', 'vendor', $this->vendor->name);
    }
}
