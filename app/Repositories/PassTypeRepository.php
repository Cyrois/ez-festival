<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\PassType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class PassTypeRepository
{
    /** @return Collection<int, PassType> */
    public function list(Event $event): Collection
    {
        return Cache::rememberForever(
            $this->listKey($event->id),
            fn (): Collection => $event->passTypes()
                ->with('labels')
                ->withCount('assignments')
                ->orderBy('name')
                ->get(),
        );
    }

    public function forgetList(int $eventId): void
    {
        Cache::forget($this->listKey($eventId));
    }

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

    private function listKey(int $eventId): string
    {
        return "lists.events.{$eventId}.pass-types.v1";
    }
}
