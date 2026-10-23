<?php

namespace App\Services;

use App\Enums\StockMovement as MovementType;
use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\StockHolder;
use App\Models\StockMovement;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\InventoryTargetsQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryMovementService
{
    public function __construct(private readonly StockMovementService $stock, private readonly InventoryTargetsQuery $targets) {}

    public function pull(Event $event, User $actor, array $data): StockMovement
    {
        return DB::transaction(function () use ($event, $actor, $data): StockMovement {
            $event = $this->lockEvent($event);
            $this->lockParticipants($event, [$actor->person_id, $data['person_id']]);
            abort_unless($this->allows($event, $actor, 'inventory.manage'), 403);
            if (! $this->targets->canReceive($event, (int) $data['person_id'])) {
                throw ValidationException::withMessages(['person_id' => __('inventory.errors.receiver_unavailable')]);
            }
            $item = $event->entitlementItems()->findOrFail($data['entitlement_item_id']);
            $from = $this->stock->forLocation($event, (int) $data['location_id']);
            $to = $this->stock->forPerson($event, Person::findOrFail($data['person_id']));

            return $this->stock->move($item, MovementType::Pull, (int) $data['quantity'], $from, $to, $actor);
        }, 3);
    }

    public function returnStock(Event $event, User $actor, array $data): StockMovement
    {
        return DB::transaction(function () use ($event, $actor, $data): StockMovement {
            $event = $this->lockEvent($event);
            $this->lockParticipants($event, [$actor->person_id, $data['person_id'] ?? null]);
            abort_unless($this->allows($event, $actor, 'checkin.edit'), 403);
            $item = $event->entitlementItems()->findOrFail($data['entitlement_item_id']);
            $from = $this->ownHolder($event, $actor);
            if (! empty($data['person_id'])) {
                if (! empty($data['location_id']) || ! $this->targets->isManagerTarget($event, (int) $data['person_id'], $actor->person_id)) {
                    throw ValidationException::withMessages(['person_id' => __('inventory.errors.manager_unavailable')]);
                }
                $to = $this->stock->forPerson($event, Person::findOrFail($data['person_id']));
            } else {
                $to = $this->stock->forLocation($event, (int) ($data['location_id'] ?? 0));
            }

            return $this->stock->move($item, MovementType::Return, (int) $data['quantity'], $from, $to, $actor);
        }, 3);
    }

    public function passStock(Event $event, User $actor, array $data): StockMovement
    {
        return DB::transaction(function () use ($event, $actor, $data): StockMovement {
            $event = $this->lockEvent($event);
            $this->lockParticipants($event, [$actor->person_id, $data['person_id']]);
            abort_unless($this->allows($event, $actor, 'inventory.pass_shift') || $this->allows($event, $actor, 'inventory.pass_group'), 403);
            if (! $this->targets->canPassTo($event, $actor, (int) $data['person_id'])) {
                throw ValidationException::withMessages(['person_id' => __('inventory.errors.pass_unavailable')]);
            }
            $item = $event->entitlementItems()->findOrFail($data['entitlement_item_id']);
            $from = $this->ownHolder($event, $actor);
            $to = $this->stock->forPerson($event, Person::findOrFail($data['person_id']));

            // A manager is always a Return destination, even when also on the giver's shift/group.
            $type = $this->targets->isManagerTarget($event, (int) $data['person_id'], $actor->person_id)
                ? MovementType::Return : MovementType::Transfer;

            return $this->stock->move($item, $type, (int) $data['quantity'], $from, $to, $actor);
        }, 3);
    }

    public function takeBack(Event $event, User $actor, StockHolder $source, array $data): StockMovement
    {
        return DB::transaction(function () use ($event, $actor, $source, $data): StockMovement {
            $event = $this->lockEvent($event);
            $source = StockHolder::where('event_id', $event->id)->whereNotNull('person_id')->findOrFail($source->id);
            $this->lockParticipants($event, [$actor->person_id, $source->person_id, $data['person_id'] ?? null]);
            abort_unless($this->allows($event, $actor, 'inventory.manage'), 403);
            $item = $event->entitlementItems()->findOrFail($data['entitlement_item_id']);
            if (($data['destination'] ?? null) === 'location') {
                $to = $this->stock->forLocation($event, (int) ($data['location_id'] ?? 0));
            } else {
                $personId = match ($data['destination'] ?? null) {
                    'me' => $actor->person_id,
                    'person' => (int) ($data['person_id'] ?? 0),
                    default => throw ValidationException::withMessages(['destination' => __('inventory.errors.invalid_movement')]),
                };
                if ($personId === $source->person_id || ! $this->targets->canReceive($event, $personId)) {
                    throw ValidationException::withMessages(['person_id' => __('inventory.errors.receiver_unavailable')]);
                }
                $to = $this->stock->forPerson($event, Person::findOrFail($personId));
            }

            return $this->stock->move($item, MovementType::TakeBack, (int) $data['quantity'], $source, $to, $actor, $data['note'] ?? null);
        }, 3);
    }

    /** Each submitted set is one transaction, including holder creation and cache invalidation. */
    public function give(Event $event, User $actor, array $data): void
    {
        DB::transaction(function () use ($event, $actor, $data): void {
            $event = $this->lockEvent($event);
            $personId = (int) $data['person_id'];
            $this->lockParticipants($event, [$actor->person_id, $personId]);
            $manager = $this->allows($event, $actor, 'inventory.manage');
            app(InventoryHoldingsAccess::class)->authorizePerson($event, $actor, $data, $personId, $manager);
            if (! $this->targets->canReceive($event, $personId)) {
                throw ValidationException::withMessages(['person_id' => __('inventory.errors.receiver_unavailable')]);
            }
            $lines = $this->batchLines($event, $data);
            $to = $this->stock->forPerson($event, Person::findOrFail($personId));
            $personType = $this->targets->isManagerTarget($event, $personId, $actor->person_id) ? MovementType::Return : MovementType::Transfer;
            foreach ($lines as $index => $line) {
                $locationId = $line['location_id'] ?? null;
                abort_unless($manager || empty($locationId), 403);
                $from = ! empty($locationId) ? $this->stock->forLocation($event, (int) $locationId)
                    : StockHolder::where('event_id', $event->id)->where('person_id', $actor->person_id)->first();
                $type = ! empty($locationId) ? MovementType::Pull
                    : $personType;
                $this->moveLine($event, $actor, $line, $index, $from, $to, $type);
            }
        }, 3);
    }

    public function takeBackItems(Event $event, User $actor, array $data): void
    {
        DB::transaction(function () use ($event, $actor, $data): void {
            $event = $this->lockEvent($event);
            $sourceId = (int) $data['person_id'];
            $receiverId = ($data['destination'] ?? null) === 'me' ? $actor->person_id : (int) ($data['receiver_id'] ?? 0);
            $this->lockParticipants($event, [$actor->person_id, $sourceId, $receiverId]);
            $manager = $this->allows($event, $actor, 'inventory.manage');
            $access = app(InventoryHoldingsAccess::class);
            $access->authorizePerson($event, $actor, $data, $sourceId, $manager);
            $lines = $this->batchLines($event, $data, true);
            $from = StockHolder::where('event_id', $event->id)->where('person_id', $sourceId)->first();
            if (($data['destination'] ?? null) === 'location') {
                $to = $this->stock->forLocation($event, (int) ($data['location_id'] ?? 0));
            } else {
                if (! in_array($data['destination'] ?? null, ['me', 'person'], true)
                    || $receiverId === $sourceId || ! $this->targets->canReceive($event, $receiverId)) {
                    throw ValidationException::withMessages(['receiver_id' => __('inventory.errors.receiver_unavailable')]);
                }
                if (! $manager) {
                    $access->authorizePerson($event, $actor, $data, $receiverId, false);
                }
                $to = $this->stock->forPerson($event, Person::findOrFail($receiverId));
            }
            foreach ($lines as $index => $line) {
                if (! empty($line['location_id'])) {
                    throw ValidationException::withMessages(['items' => __('inventory.errors.invalid_movement')]);
                }
                $this->moveLine($event, $actor, $line, $index, $from, $to, MovementType::TakeBack, $data['note'] ?? null);
            }
        }, 3);
    }

    public function adjustInventory(Event $event, User $actor, array $data): void
    {
        DB::transaction(function () use ($event, $actor, $data): void {
            $event = $this->lockEvent($event);
            $personId = (int) $data['person_id'];
            $this->lockParticipants($event, [$actor->person_id, $personId]);
            abort_unless($this->allows($event, $actor, 'inventory.manage'), 403);
            $lines = $this->batchLines($event, $data);
            $direction = $data['direction'] ?? null;
            if (! in_array($direction, ['from_location', 'to_location'], true)) {
                throw ValidationException::withMessages(['direction' => __('inventory.errors.invalid_movement')]);
            }
            $location = $this->stock->forLocation($event, (int) ($data['location_id'] ?? 0));
            if ($direction === 'from_location') {
                if (! $this->targets->canReceive($event, $personId) || filled($data['note'] ?? null)) {
                    throw ValidationException::withMessages(['person_id' => __('inventory.errors.receiver_unavailable')]);
                }
                $person = $this->stock->forPerson($event, Person::findOrFail($personId));
            } else {
                $person = StockHolder::where('event_id', $event->id)->where('person_id', $personId)->first();
            }
            $type = $direction === 'from_location' ? MovementType::Pull
                : ($personId === $actor->person_id ? MovementType::Return : MovementType::TakeBack);
            foreach ($lines as $index => $line) {
                $this->moveLine($event, $actor, $line, $index,
                    $direction === 'from_location' ? $location : $person,
                    $direction === 'from_location' ? $person : $location,
                    $type, $direction === 'to_location' ? ($data['note'] ?? null) : null);
            }
        }, 3);
    }

    private function batchLines(Event $event, array $data, bool $allowZero = false): array
    {
        $lines = $data['items'] ?? [];
        if (! is_array($lines) || $lines === []) {
            throw ValidationException::withMessages(['items' => __('inventory.errors.items_required')]);
        }
        $ids = [];
        foreach ($lines as $index => $line) {
            $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < ($allowZero ? 0 : 1) || $quantity > 2147483647) {
                throw ValidationException::withMessages(["items.$index.quantity" => __('inventory.errors.quantity_invalid')]);
            }
            $id = (int) ($line['entitlement_item_id'] ?? 0);
            if (in_array($id, $ids, true) || ! $event->entitlementItems()->whereKey($id)->exists()) {
                throw ValidationException::withMessages(["items.$index.entitlement_item_id" => __('inventory.errors.invalid_movement')]);
            }
            $ids[] = $id;
            $lines[$index]['quantity'] = $quantity;
        }
        $lines = array_filter($lines, fn ($line) => $line['quantity'] > 0);
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => __('inventory.errors.items_required')]);
        }

        return $lines;
    }

    private function moveLine(Event $event, User $actor, array $line, int $index, ?StockHolder $from, StockHolder $to, MovementType $type, ?string $note = null): void
    {
        $item = $event->entitlementItems()->findOrFail($line['entitlement_item_id']);
        $available = $from === null ? 0 : $this->stock->balance($item, $from);
        if ($line['quantity'] > $available) {
            throw ValidationException::withMessages(["items.$index.quantity" => __('inventory.errors.insufficient_stock', ['count' => $available])]);
        }
        $this->stock->move($item, $type, $line['quantity'], $from, $to, $actor, $note);
    }

    private function ownHolder(Event $event, User $actor): StockHolder
    {
        return StockHolder::where('event_id', $event->id)->where('person_id', $actor->person_id)->first()
            ?? throw ValidationException::withMessages(['quantity' => __('credentials.entitlements.errors.insufficient_stock')]);
    }

    private function lockEvent(Event $event): Event
    {
        $event = Event::query()->lockForUpdate()->findOrFail($event->id);
        $event->ensureWritable();

        return $event;
    }

    private function lockParticipants(Event $event, array $personIds): void
    {
        $members = TeamEngagement::where('event_id', $event->id)->whereIn('person_id', $personIds)
            ->orderBy('id')->lockForUpdate()->get();
        Role::whereIn('id', $members->pluck('role_id')->filter())->orderBy('id')->lockForUpdate()->get();
        Person::whereIn('id', $personIds)->orderBy('id')->lockForUpdate()->get();
        User::whereIn('person_id', $personIds)->orderBy('id')->lockForUpdate()->get();
    }

    /** Fresh transaction checks; request-cached permissions are only for preflight/UI. */
    private function allows(Event $event, User $actor, string $permission): bool
    {
        return in_array($permission, $this->targets->permissions($event, $actor), true);
    }
}
