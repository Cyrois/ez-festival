<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventStockItem;
use App\Models\EventStockItemLabel;
use App\Models\EventStockItemMovement;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventStockItemService
{
    /**
     * @param  array{name: string, opening_balance: int}  $data
     */
    public function create(Event $event, array $data, User $actor): EventStockItem
    {
        return DB::transaction(function () use ($event, $data, $actor): EventStockItem {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            try {
                $item = $event->stockItems()->create([
                    'name' => $data['name'],
                    'balance' => $data['opening_balance'],
                ]);

                $item->movements()->create([
                    'kind' => EventStockItemMovement::KIND_OPENING,
                    'quantity_delta' => $data['opening_balance'],
                    'balance_after' => $data['opening_balance'],
                    'created_by_user_id' => $actor->id,
                ]);
                $this->syncLabels($item, $data, $event);

                return $item;
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => __('credentials.entitlements.errors.name_taken'),
                ]);
            }
        });
    }

    public function update(EventStockItem $item, array $data): void
    {
        DB::transaction(function () use ($item, $data): void {
            $item = EventStockItem::query()->lockForUpdate()->findOrFail($item->id);
            $event = Event::query()->lockForUpdate()->findOrFail($item->event_id);
            $event->ensureWritable();

            try {
                $item->update(['name' => $data['name']]);
                $this->syncLabels($item, $data, $event);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => __('credentials.entitlements.errors.name_taken'),
                ]);
            }
        });
    }

    private function syncLabels(EventStockItem $item, array $data, Event $event): void
    {
        $labelIds = $data['label_ids'] ?? [];

        foreach ($data['new_labels'] ?? [] as $label) {
            $saved = $event->stockItemLabels()->firstOrCreate(
                ['name_key' => EventStockItemLabel::normalizeName($label['name'])],
                ['name' => $label['name'], 'color' => $label['color']],
            );
            $labelIds[] = $saved->id;
        }

        $item->labels()->sync(array_values(array_unique($labelIds)));
    }

    public function adjust(EventStockItem $item, int $quantityDelta, string $reason, User $actor): void
    {
        DB::transaction(function () use ($item, $quantityDelta, $reason, $actor): void {
            $item = EventStockItem::query()->lockForUpdate()->findOrFail($item->id);
            $event = Event::query()->lockForUpdate()->findOrFail($item->event_id);
            $event->ensureWritable();

            $balance = $item->balance + $quantityDelta;

            if ($balance < 0) {
                throw ValidationException::withMessages([
                    'quantity' => __('credentials.entitlements.errors.insufficient_stock'),
                ]);
            }

            $item->update(['balance' => $balance]);
            $item->movements()->create([
                'kind' => EventStockItemMovement::KIND_ADJUSTMENT,
                'quantity_delta' => $quantityDelta,
                'balance_after' => $balance,
                'reason' => $reason,
                'created_by_user_id' => $actor->id,
            ]);
        });
    }
}
