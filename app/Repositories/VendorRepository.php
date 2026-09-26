<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\VendorEngagement;
use Illuminate\Database\Eloquent\Collection;

class VendorRepository
{
    /** @return Collection<int, VendorEngagement> */
    public function allFor(?Event $event): Collection
    {
        return VendorEngagement::query()
            ->where('event_id', $event?->id)
            ->with(['vendor', 'vendorType'])
            ->get();
    }
}
