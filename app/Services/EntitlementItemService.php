<?php

namespace App\Services;

use App\Models\ArtistLabel;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntitlementItemService
{
    /** @param array<string, mixed> $data */
    public function create(Event $event, array $data, User $actor): EntitlementItem
    {
        return DB::transaction(function () use ($event, $data, $actor): EntitlementItem {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            $item = $event->entitlementItems()->create(['name' => $data['name']]);

            if (($data['opening_balance'] ?? 0) > 0) {
                $item->adjustments()->create([
                    'delta' => $data['opening_balance'],
                    'user_id' => $actor->id,
                ]);
            }

            $this->syncLabels($item, $data);

            return $item;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(EntitlementItem $item, array $data): void
    {
        DB::transaction(function () use ($item, $data): void {
            $item = EntitlementItem::query()->lockForUpdate()->findOrFail($item->id);
            $event = Event::query()->lockForUpdate()->findOrFail($item->event_id);
            $event->ensureWritable();

            $item->update(['name' => $data['name']]);
            $this->syncLabels($item, $data);
        });
    }

    /** @param array<string, mixed> $data */
    private function syncLabels(EntitlementItem $item, array $data): void
    {
        $labelIds = $data['label_ids'] ?? [];

        foreach ($data['new_labels'] ?? [] as $label) {
            $saved = ArtistLabel::query()->firstOrCreate(
                ['name_key' => ArtistLabel::normalizeName($label['name'])],
                ['name' => $label['name'], 'color' => $label['color']],
            );
            $labelIds[] = $saved->id;
        }

        $item->labels()->sync(array_values(array_unique($labelIds)));
    }

    public function adjust(EntitlementItem $item, int $delta, ?string $reason, User $actor): void
    {
        DB::transaction(function () use ($item, $delta, $reason, $actor): void {
            $item = EntitlementItem::query()->lockForUpdate()->findOrFail($item->id);
            $event = Event::query()->lockForUpdate()->findOrFail($item->event_id);
            $event->ensureWritable();

            if ($this->balance($item) + $delta < 0) {
                throw ValidationException::withMessages([
                    'quantity' => __('credentials.entitlements.errors.insufficient_stock'),
                ]);
            }

            $item->adjustments()->create([
                'delta' => $delta,
                'reason' => $reason,
                'user_id' => $actor->id,
            ]);
        });
    }

    public function balance(EntitlementItem $item): int
    {
        return (int) $item->adjustments()->sum('delta');
    }

    public function destroy(EntitlementItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $item = EntitlementItem::query()->lockForUpdate()->findOrFail($item->id);
            $event = Event::query()->lockForUpdate()->findOrFail($item->event_id);
            $event->ensureWritable();

            if ($item->passTypeEntitlements()->exists()
                || $item->expectedEntitlements()->exists()
                || $item->issuedEntitlements()->exists()
                || $this->balance($item) !== 0) {
                throw ValidationException::withMessages([
                    'item' => __('credentials.entitlements.errors.delete_blocked'),
                ]);
            }

            $item->delete();
        });
    }
}
