<?php

namespace Tests;

use App\Enums\StockMovement as StockMovementType;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\Location;
use App\Models\Role;
use App\Models\StockHolder;
use App\Models\StockMovement;
use App\Models\TeamEngagement;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
                ['role_id' => $role->id, 'status' => 'hired', 'employment_type' => 'volunteer'],
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
