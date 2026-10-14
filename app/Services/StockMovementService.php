<?php

namespace App\Services;

use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\IssuedEntitlement;
use App\Models\Location;
use App\Models\Person;
use App\Models\StockHolder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    public function forLocation(Event $event, int $locationId): StockHolder
    {
        return DB::transaction(function () use ($event, $locationId): StockHolder {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            if ($event->locations()->whereKey($locationId)->lockForUpdate()->first(['id']) === null) {
                throw ValidationException::withMessages(['location_id' => __('credentials.entitlements.errors.location_unavailable')]);
            }

            return StockHolder::query()->firstOrCreate(['event_id' => $event->id, 'location_id' => $locationId]);
        }, 3);
    }

    /** Membership and receiver eligibility belong to the calling feature, not holder identity. */
    public function forPerson(Event $event, Person $person): StockHolder
    {
        return DB::transaction(function () use ($event, $person): StockHolder {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();
            Person::query()->lockForUpdate()->findOrFail($person->id);

            return StockHolder::query()->firstOrCreate(['event_id' => $event->id, 'person_id' => $person->id]);
        }, 3);
    }

    public function move(
        EntitlementItem $item,
        string $type,
        int $quantity,
        ?StockHolder $from,
        ?StockHolder $to,
        User $actor,
        ?string $note = null,
        ?IssuedEntitlement $issued = null,
    ): StockMovement {
        return DB::transaction(function () use ($item, $type, $quantity, $from, $to, $actor, $note, $issued): StockMovement {
            $event = Event::query()->lockForUpdate()->findOrFail($item->event_id);
            $event->ensureWritable();
            $item = $event->entitlementItems()->lockForUpdate()->findOrFail($item->id);
            $ids = array_filter([$from?->id, $to?->id]);
            $holders = StockHolder::query()->where('event_id', $event->id)->whereKey($ids)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($holders->count() !== count(array_unique($ids))) {
                throw ValidationException::withMessages(['holder' => __('inventory.errors.holder_unavailable')]);
            }
            $from = $from === null ? null : $holders->get($from->id);
            $to = $to === null ? null : $holders->get($to->id);
            $note = filled($note) ? trim($note) : null;

            if ($quantity < 1 || $quantity > 2147483647) {
                throw ValidationException::withMessages(['quantity' => __('inventory.errors.quantity_invalid')]);
            }
            if ($note !== null && mb_strlen($note) > 255) {
                throw ValidationException::withMessages(['reason' => __('validation.max.string', ['attribute' => __('credentials.entitlements.fields.reason'), 'max' => 255])]);
            }
            $validShape = match ($type) {
                StockMovement::ADD => $from === null && $to?->location_id !== null && $issued === null,
                StockMovement::REMOVE => $from?->location_id !== null && $to === null && $issued === null,
                StockMovement::GIVE_OUT => $from !== null && $to === null && $issued !== null && $quantity === 1,
                StockMovement::PULL, StockMovement::RETURN, StockMovement::TRANSFER, StockMovement::TAKE_BACK => $from !== null && $to !== null && $from->id !== $to->id && $issued === null,
                default => false,
            };
            if (! $validShape) {
                throw ValidationException::withMessages(['movement' => __('inventory.errors.invalid_movement')]);
            }
            if ($type === StockMovement::REMOVE && ! filled($note)) {
                throw ValidationException::withMessages(['reason' => __('inventory.errors.reason_required')]);
            }
            if ($issued !== null && ! IssuedEntitlement::query()->whereKey($issued->id)->where('entitlement_item_id', $item->id)->exists()) {
                throw ValidationException::withMessages(['issued_entitlement' => __('inventory.errors.issue_unavailable')]);
            }
            if ($issued !== null && StockMovement::query()->where('issued_entitlement_id', $issued->id)->exists()) {
                throw ValidationException::withMessages(['expected_entitlement' => __('credentials.entitlements.errors.already_consumed')]);
            }
            if ($from !== null && $this->balance($item, $from) < $quantity) {
                throw ValidationException::withMessages(['quantity' => __('credentials.entitlements.errors.insufficient_stock')]);
            }

            $movement = StockMovement::query()->create([
                'event_id' => $event->id,
                'entitlement_item_id' => $item->id,
                'quantity' => $quantity,
                'from_holder_id' => $from?->id,
                'to_holder_id' => $to?->id,
                'type' => $type,
                'actor_user_id' => $actor->id,
                'note' => $note,
                'issued_entitlement_id' => $issued?->id,
            ]);
            DB::afterCommit(fn () => app(EntitlementItemService::class)->forgetList($event->id));

            return $movement;
        }, 3);
    }

    /** Signed ledger entries, always scoped before aggregation and never hydrated as history. */
    public function entriesQuery(int $eventId): Builder
    {
        $incoming = DB::table('stock_movements')->where('event_id', $eventId)->whereNotNull('to_holder_id')
            ->select('entitlement_item_id', 'to_holder_id as holder_id')->selectRaw('CAST(quantity AS BIGINT) as delta');
        $outgoing = DB::table('stock_movements')->where('event_id', $eventId)->whereNotNull('from_holder_id')
            ->select('entitlement_item_id', 'from_holder_id as holder_id')->selectRaw('-CAST(quantity AS BIGINT) as delta');

        return DB::query()->fromSub($incoming->unionAll($outgoing), 'stock_entries');
    }

    public function balancesQuery(int $eventId): Builder
    {
        return $this->entriesQuery($eventId)->select('entitlement_item_id', 'holder_id')
            ->selectRaw('SUM(delta) as balance')->groupBy('entitlement_item_id', 'holder_id');
    }

    public function locationBalancesQuery(int $eventId): Builder
    {
        return DB::query()->fromSub($this->balancesQuery($eventId), 'stock_balances')
            ->join('stock_holders as sh', 'sh.id', '=', 'stock_balances.holder_id')
            ->join('locations as l', 'l.id', '=', 'sh.location_id')
            ->where('sh.event_id', $eventId)
            ->select('stock_balances.entitlement_item_id', 'sh.location_id', 'l.name', 'stock_balances.balance');
    }

    public function balance(EntitlementItem $item, StockHolder $holder): int
    {
        abort_unless($item->event_id === $holder->event_id, 404);

        return (int) $this->entriesQuery($item->event_id)->where('entitlement_item_id', $item->id)->where('holder_id', $holder->id)->sum('delta');
    }

    public function total(EntitlementItem $item): int
    {
        return (int) $this->entriesQuery($item->event_id)->where('entitlement_item_id', $item->id)->sum('delta');
    }

    public function locationHasHistory(Location $location): bool
    {
        return StockHolder::query()->where('event_id', $location->event_id)->where('location_id', $location->id)->get()
            ->contains(fn (StockHolder $holder): bool => $holder->hasHistory());
    }
}
