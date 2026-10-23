<?php

namespace App\Services;

use App\Enums\StockMovement as StockMovementType;
use App\Models\EntitlementItem;
use App\Models\EntitlementItemLabel;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntitlementItemService
{
    public function __construct(private readonly StockMovementService $stocks) {}

    /** @return Collection<int, EntitlementItem> */
    public function list(Event $event): Collection
    {
        $rows = Cache::rememberForever(
            $this->listKey($event->id),
            fn (): array => $event->entitlementItems()
                ->with('labels')
                ->addSelect(['balance' => $this->stocks->entriesQuery($event->id)
                    ->whereColumn('stock_entries.entitlement_item_id', 'entitlement_items.id')
                    ->selectRaw('COALESCE(SUM(delta), 0)')])
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

            $opening = (int) ($data['opening_balance'] ?? 0);
            if ($opening < 0 || $opening > 2147483647) {
                throw ValidationException::withMessages(['opening_balance' => __('inventory.errors.quantity_invalid')]);
            }
            $item = $event->entitlementItems()->create(['name' => $data['name'], 'low_stock_level' => $data['low_stock_level'] ?? null]);

            if ($opening > 0) {
                $this->stocks->move($item, StockMovementType::Add, $opening, null,
                    $this->stocks->forLocation($event, (int) ($data['location_id'] ?? 0)), $actor);
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

            $item->update([
                'name' => $data['name'],
                'needs_unique_code' => $data['needs_unique_code'] ?? $item->needs_unique_code,
                'low_stock_level' => array_key_exists('low_stock_level', $data) ? $data['low_stock_level'] : $item->low_stock_level,
            ]);
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
        DB::transaction(function () use ($item, $locationId, $delta, $reason, $actor): void {
            $event = Event::query()->lockForUpdate()->findOrFail($item->event_id);
            $event->ensureWritable();
            $item = $event->entitlementItems()->lockForUpdate()->findOrFail($item->id);
            if ($delta === 0 || $delta < -2147483647 || $delta > 2147483647) {
                throw ValidationException::withMessages(['quantity' => __('inventory.errors.quantity_invalid')]);
            }
            $holder = $this->stocks->forLocation($event, $locationId);
            $this->stocks->move($item, $delta < 0 ? StockMovementType::Remove : StockMovementType::Add,
                abs($delta), $delta < 0 ? $holder : null, $delta < 0 ? null : $holder, $actor, $reason);
        }, 3);
    }

    public function balance(EntitlementItem $item): int
    {
        return $this->stocks->total($item);
    }

    public function balanceTotal(EntitlementItem $item): int
    {
        return $this->balance($item);
    }

    public function balanceForLocation(EntitlementItem $item, int $locationId): int
    {
        return (int) $this->stocks->locationBalancesQuery($item->event_id)
            ->where('stock_balances.entitlement_item_id', $item->id)->where('sh.location_id', $locationId)->first()?->balance;
    }

    /** @return array<int, int> */
    public function balanceByLocation(EntitlementItem $item): array
    {
        return $this->stocks->locationBalancesQuery($item->event_id)
            ->where('stock_balances.entitlement_item_id', $item->id)->pluck('balance', 'location_id')
            ->map(fn ($balance): int => (int) $balance)->all();
    }

    public function deletionBlocked(EntitlementItem $item): bool
    {
        return $item->passTypeEntitlements()->exists()
            || $item->expectedEntitlements()->exists()
            || $item->issuedEntitlements()->exists()
            || $this->stocks->balancesQuery($item->event_id)->where('entitlement_item_id', $item->id)
                ->havingRaw('SUM(delta) <> 0')->exists();
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

            if ($this->deletionBlocked($item)) {
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
        DB::afterCommit(fn () => Cache::forget($this->listKey($eventId)));
    }

    private function listKey(int $eventId): string
    {
        return "lists.events.{$eventId}.entitlement-items.v6";
    }
}
