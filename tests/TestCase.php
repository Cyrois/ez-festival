<?php

namespace Tests;

use App\Enums\StockMovement as StockMovementType;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\Group;
use App\Models\Location;
use App\Models\Role;
use App\Models\StockHolder;
use App\Models\StockMovement;
use App\Models\TeamEngagement;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Create a member fixture with the event-scoped group required for hiring. */
    protected function createTeamEngagement(array $attributes): TeamEngagement
    {
        if (($attributes['status'] ?? 'applied') === 'hired' && ! array_key_exists('group_id', $attributes)) {
            $attributes['group_id'] = $this->fixtureGroupId($attributes['event_id']);
        }

        return TeamEngagement::query()->create($attributes);
    }

    protected function fixtureGroupId(int $eventId): int
    {
        return Group::query()->where('event_id', $eventId)->value('id')
            ?? Group::query()->create(['event_id' => $eventId, 'name' => 'Test group'])->id;
    }

    /** Distribute fixture stock to the actor without bypassing ledger constraints. */
    protected function seedHeldStock(EntitlementItem $item, Location $location, int $quantity, User $actor): StockHolder
    {
        $this->seedStock($item, $location, $quantity, $actor);
        $from = StockHolder::where('event_id', $item->event_id)->where('location_id', $location->id)->sole();
        $holder = StockHolder::firstOrCreate(['event_id' => $item->event_id, 'person_id' => $actor->person_id]);
        $item->movements()->create([
            'event_id' => $item->event_id, 'quantity' => $quantity, 'type' => StockMovementType::Pull,
            'from_holder_id' => $from->id, 'to_holder_id' => $holder->id, 'actor_user_id' => $actor->id,
        ]);

        return $holder;
    }

    /** Seed a valid ledger fixture without coupling setup to the write service under test. */
    protected function seedStock(EntitlementItem $item, Location $location, int $delta, ?User $actor = null): StockMovement
    {
        $holder = StockHolder::query()->firstOrCreate(['event_id' => $item->event_id, 'location_id' => $location->id]);

        return $item->movements()->create([
            'event_id' => $item->event_id,
            'quantity' => abs($delta),
            'type' => $delta > 0 ? StockMovementType::Add : StockMovementType::Remove,
            'from_holder_id' => $delta < 0 ? $holder->id : null,
            'to_holder_id' => $delta > 0 ? $holder->id : null,
            'actor_user_id' => $actor?->id ?? User::query()->value('id'),
            'note' => $delta < 0 ? 'Test stock count' : null,
        ]);
    }

    protected function grantRoleAccess(User $user, array $permissions = ['team.view', 'artists.view', 'checkin.view']): User
    {
        $user->forceFill(['is_admin' => false])->save();
        $user->person()->update(['can_log_in' => true]);
        $role = Role::query()->updateOrCreate(['name_key' => 'test access '.$user->id], ['name' => 'Test access '.$user->id, 'permissions' => $permissions]);
        foreach (Event::all() as $event) {
            TeamEngagement::query()->updateOrCreate(
                ['event_id' => $event->id, 'person_id' => $user->person_id],
                ['role_id' => $role->id, 'group_id' => TeamEngagement::where('event_id', $event->id)->where('person_id', $user->person_id)->value('group_id') ?? $this->fixtureGroupId($event->id), 'status' => 'hired', 'employment_type' => 'volunteer'],
            );
        }

        return $user->fresh();
    }

    protected function grantAdminAccess(User $user): User
    {
        $user->forceFill(['is_admin' => true])->save();
        $user->person()->update(['can_log_in' => true]);

        return $user->fresh();
    }
}
