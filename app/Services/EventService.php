<?php

namespace App\Services;

use App\Models\Event;
use App\Repositories\PassTypeRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EventService
{
    private const LIST_KEY = 'lists.events.v2';

    public function __construct(
        private readonly EntitlementItemService $entitlementItems,
        private readonly PassTypeRepository $passTypes,
    ) {}

    /** @return Collection<int, Event> */
    public function list(): Collection
    {
        $rows = Cache::rememberForever(
            self::LIST_KEY,
            fn (): array => Event::query()
                ->orderByDesc('starts_on')
                ->orderByDesc('id')
                ->get()->map(fn (Event $event) => $event->getAttributes())->all(),
        );

        return Event::hydrate($rows);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Event
    {
        $event = Event::query()->create($data);
        $this->forgetList();

        return $event;
    }

    /** @param array<string, mixed> $data */
    public function update(Event $event, array $data): void
    {
        DB::transaction(function () use ($event, $data): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            $event->update($data);
        });
        $this->forgetList();
    }

    public function lock(Event $event): void
    {
        DB::transaction(fn () => Event::query()->lockForUpdate()->findOrFail($event->id)->lock());
        $this->forgetList();
    }

    public function unlock(Event $event): void
    {
        DB::transaction(fn () => Event::query()->lockForUpdate()->findOrFail($event->id)->unlock());
        $this->forgetList();
    }

    public function delete(Event $event): void
    {
        $eventId = $event->id;

        DB::transaction(function () use ($event): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            $entitlementItemIds = fn () => DB::table('entitlement_items')
                ->select('id')
                ->where('event_id', $event->id);
            $locationIds = fn () => DB::table('locations')
                ->select('id')
                ->where('event_id', $event->id);
            $passTypeIds = fn () => DB::table('pass_types')
                ->select('id')
                ->where('event_id', $event->id);

            DB::table('issued_entitlements')
                ->where(fn ($query) => $query
                    ->whereIn('entitlement_item_id', $entitlementItemIds())
                    ->orWhereIn('location_id', $locationIds()))
                ->delete();

            DB::table('expected_entitlements')
                ->whereIn('entitlement_item_id', $entitlementItemIds())
                ->delete();

            DB::table('entitlement_adjustments')
                ->where(fn ($query) => $query
                    ->whereIn('entitlement_item_id', $entitlementItemIds())
                    ->orWhereIn('location_id', $locationIds()))
                ->delete();

            DB::table('pass_type_entitlements')
                ->where(fn ($query) => $query
                    ->whereIn('pass_type_id', $passTypeIds())
                    ->orWhereIn('entitlement_item_id', $entitlementItemIds()))
                ->delete();

            DB::table('pass_assignments')
                ->whereIn('pass_type_id', $passTypeIds())
                ->delete();

            $event->shifts()->delete();
            $event->teamEngagements()->delete();
            $event->delete();
        });

        $this->entitlementItems->forgetList($eventId);
        $this->passTypes->forgetList($eventId);
        $this->forgetList();
    }

    private function forgetList(): void
    {
        Cache::forget(self::LIST_KEY);
    }
}
