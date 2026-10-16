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
