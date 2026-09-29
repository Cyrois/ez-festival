<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\Location;
use Illuminate\Support\Collection;

class LocationRepository
{
    /** @return Collection<int, Location> */
    public function optionsFor(Event $event): Collection
    {
        return $event->locations()
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
