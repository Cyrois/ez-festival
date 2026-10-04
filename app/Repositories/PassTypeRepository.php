<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\PassType;
use App\Models\PassTypeLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PassTypeRepository
{
    /** @return Collection<int, PassType> */
    public function list(Event $event): Collection
    {
        $rows = Cache::rememberForever(
            $this->listKey($event->id),
            fn (): array => $event->passTypes()
                ->with('labels')
                ->withCount('assignments')
                ->orderBy('name')
                ->get()->map(fn (PassType $pass) => [
                    'attributes' => $pass->getAttributes(),
                    'labels' => $pass->labels->map(fn (PassTypeLabel $label) => $label->getAttributes())->all(),
                ])->all(),
        );

        return PassType::hydrate(array_column($rows, 'attributes'))
            ->each(fn (PassType $pass, int $index) => $pass->setRelation('labels', PassTypeLabel::hydrate($rows[$index]['labels'])));
    }

    public function forgetList(int $eventId): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => Cache::forget($this->listKey($eventId)));

            return;
        }

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
        return "lists.events.{$eventId}.pass-types.v2";
    }
}
