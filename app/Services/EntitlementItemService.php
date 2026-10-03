<?php

namespace App\Services;

use App\Models\EntitlementItem;
use App\Models\EntitlementItemLabel;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntitlementItemService
{
    /** @return Collection<int, EntitlementItem> */
    public function list(Event $event): Collection
    {
        $rows = Cache::rememberForever(
            $this->listKey($event->id),
            fn (): array => $event->entitlementItems()
                ->with('labels')
                ->withSum('adjustments as balance', 'delta')
                ->orderBy('name')
                ->get()->map(fn (EntitlementItem $item) => [
                    'attributes' => $item->getAttributes(),
                    'labels' => $item->labels->map(fn (EntitlementItemLabel $label) => $label->getAttributes())->all(),
                ])->all(),
        );

        return EntitlementItem::hydrate(array_column($rows, 'attributes'))
            ->each(fn (EntitlementItem $item, int $index) => $item->setRelation('labels', EntitlementItemLabel::hydrate($rows[$index]['labels'])));
    }

    /** @param array<string, mixed> $data */
    public function create(Event $event, array $data, User $actor): EntitlementItem
    {
        $item = DB::transaction(function () use ($event, $data, $actor): EntitlementItem {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            $item = $event->entitlementItems()->create(['name' => $data['name']]);

            if (($data['opening_balance'] ?? 0) > 0) {
                $item->adjustments()->create([
                    'location_id' => $data['location_id'],
                    'delta' => $data['opening_balance'],
                    'user_id' => $actor->id,
                ]);
            }

            $this->syncLabels($item, $data, $event);

            return $item;
        }, 3);

        $this->forgetList($event->id);

        return $item;
    }

    /** @param array<string, mixed> $data */
    public function update(EntitlementItem $item, array $data): void
    {
        $eventId = $item->event_id;

        DB::transaction(function () use ($item, $data): void {
            $event = Event::query()->lockForUpdate()->findOrFail($item->event_id);
            $event->ensureWritable();
            $item = $event->entitlementItems()->lockForUpdate()->findOrFail($item->id);

            $item->update(['name' => $data['name']]);
            $this->syncLabels($item, $data, $event);
        }, 3);

        $this->forgetList($eventId);
    }

    /** @param array<string, mixed> $data */
    private function syncLabels(EntitlementItem $item, array $data, Event $event): void
    {
        $labelIds = $data['label_ids'] ?? [];

        foreach ($data['new_labels'] ?? [] as $label) {
            $saved = $event->entitlementItemLabels()->firstOrCreate(
                ['name_key' => EntitlementItemLabel::normalizeName($label['name'])],
                ['name' => $label['name'], 'color' => $label['color']],
            );
            $labelIds[] = $saved->id;
        }

        $item->labels()->sync(array_values(array_unique($labelIds)));
    }

    public function adjust(EntitlementItem $item, int $locationId, int $delta, ?string $reason, User $actor): void
    {
        $eventId = $item->event_id;

        DB::transaction(function () use ($item, $locationId, $delta, $reason, $actor): void {
            $event = Event::query()->lockForUpdate()->findOrFail($item->event_id);
            $event->ensureWritable();
            $item = $event->entitlementItems()->lockForUpdate()->findOrFail($item->id);

            $location = Location::query()
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->find($locationId);

            if ($location === null) {
                throw ValidationException::withMessages([
                    'location_id' => __('credentials.entitlements.errors.location_unavailable'),
                ]);
            }

            if ($this->balanceForLocation($item, $locationId) + $delta < 0) {
                throw ValidationException::withMessages([
                    'quantity' => __('credentials.entitlements.errors.insufficient_stock'),
                ]);
            }

            $item->adjustments()->create([
                'location_id' => $locationId,
                'delta' => $delta,
                'reason' => $reason,
                'user_id' => $actor->id,
            ]);
        }, 3);

        $this->forgetList($eventId);
    }

    public function balance(EntitlementItem $item): int
    {
        return (int) $item->adjustments()->sum('delta');
    }

    public function balanceTotal(EntitlementItem $item): int
    {
        return $this->balance($item);
    }

    public function balanceForLocation(EntitlementItem $item, ?int $locationId): int
    {
        $adjustments = $item->adjustments();

        if ($locationId === null) {
            $adjustments->whereNull('location_id');
        } else {
            $adjustments->where('location_id', $locationId);
        }

        return (int) $adjustments->sum('delta');
    }

    /** @return array<int, int> */
    public function balanceByLocation(EntitlementItem $item): array
    {
        return $item->adjustments()
            ->whereNotNull('location_id')
            ->select('location_id')
            ->selectRaw('SUM(delta) as balance')
            ->groupBy('location_id')
            ->pluck('balance', 'location_id')
            ->map(fn ($balance): int => (int) $balance)
            ->all();
    }

    public function expectedCount(EntitlementItem $item): int
    {
        return $item->expectedEntitlements()
            ->where('status', ExpectedEntitlement::STATUS_EXPECTED)
            ->count();
    }

    public function issuedCount(EntitlementItem $item): int
    {
        return $item->issuedEntitlements()->count();
    }

    /** @return array<int, array{pass_type_name: string, line_count: int}> */
    public function passLineUsage(EntitlementItem $item): array
    {
        return $item->passTypeEntitlements()
            ->select('pass_type_id')
            ->selectRaw('COUNT(*) as line_count')
            ->with('passType:id,name')
            ->groupBy('pass_type_id')
            ->get()
            ->map(fn ($usage): array => [
                'pass_type_name' => $usage->passType->name,
                'line_count' => (int) $usage->line_count,
            ])
            ->values()
            ->all();
    }

    public function destroy(EntitlementItem $item): void
    {
        $eventId = $item->event_id;

        DB::transaction(function () use ($item): void {
            $event = Event::query()->lockForUpdate()->findOrFail($item->event_id);
            $event->ensureWritable();
            $item = $event->entitlementItems()->lockForUpdate()->findOrFail($item->id);

            if ($item->passTypeEntitlements()->exists()
                || $item->expectedEntitlements()->exists()
                || $item->issuedEntitlements()->exists()
                || $this->balance($item) !== 0) {
                throw ValidationException::withMessages([
                    'item' => __('credentials.entitlements.errors.delete_blocked'),
                ]);
            }

            $item->delete();
        }, 3);

        $this->forgetList($eventId);
    }

    public function forgetList(int $eventId): void
    {
        Cache::forget($this->listKey($eventId));
    }

    private function listKey(int $eventId): string
    {
        return "lists.events.{$eventId}.entitlement-items.v2";
    }
}
