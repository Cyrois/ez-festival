<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\PassType;
use Illuminate\Database\Eloquent\Collection;

class PassTypeRepository
{
    /** @return Collection<int, PassType> */
    public function optionsFor(Event $event, bool $withEntitlements = false): Collection
    {
        return $event->passTypes()
            ->when(
                $withEntitlements,
                fn ($query) => $query->with([
                    'labels:id,name,color',
                    'entitlements.entitlementItem.labels:id,name,color',
                ]),
            )
            ->withCount('assignments')
            ->orderBy('name')
            ->get(['id', 'name', 'max_assignments']);
    }
}
