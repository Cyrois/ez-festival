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
use App\Support\Permissions;
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
            $this->lockParticipants($event, [$actor->person_id]);
            abort_unless($this->allows($event, $actor, 'checkin.edit'), 403);
            $item = $event->entitlementItems()->findOrFail($data['entitlement_item_id']);
            $from = StockHolder::where('event_id', $event->id)->where('person_id', $actor->person_id)->first();
            if ($from === null) {
                throw ValidationException::withMessages(['quantity' => __('credentials.entitlements.errors.insufficient_stock')]);
            }
            $to = $this->stock->forLocation($event, (int) $data['location_id']);

            return $this->stock->move($item, MovementType::Return, (int) $data['quantity'], $from, $to, $actor);
        }, 3);
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
        $user = User::findOrFail($actor->id);
        if ($user->person_id !== $actor->person_id || ! $user->person?->can_log_in) {
            return false;
        }
        if ($user->is_admin) {
            return true;
        }
        $member = TeamEngagement::with('role')->where('event_id', $event->id)->where('person_id', $user->person_id)->first();

        return $member?->status === 'hired' && $member->role?->active
            && in_array($permission, Permissions::expand($member->role->permissions ?? []), true);
    }
}
