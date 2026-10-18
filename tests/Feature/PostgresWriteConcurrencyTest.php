<?php

namespace Tests\Feature;

use App\Enums\StockMovement as StockMovementType;
use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\MealAssignment;
use App\Models\Person;
use App\Models\Role;
use App\Models\StockHolder;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\EngagementPersonService;
use App\Services\EntitlementConsumeService;
use App\Services\EntitlementItemService;
use App\Services\EventService;
use App\Services\GlobalTeamService;
use App\Services\InventoryMovementService;
use App\Services\MealAssignmentService;
use App\Services\MealClaimService;
use App\Services\MealOverrideService;
use App\Services\MealTypeService;
use App\Services\PassAssignmentService;
use App\Services\PassTypeService;
use App\Services\RoleService;
use App\Services\StockMovementService;
use App\Services\TeamEngagementService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PostgresWriteConcurrencyTest extends TestCase
{
    use DatabaseMigrations {
        runDatabaseMigrations as private migrateTestDatabase;
    }

    public function runDatabaseMigrations(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires PostgreSQL and pcntl for two independent connections.');
        }
        $this->assertMatchesRegularExpression('/(?:_test|_audit_[a-zA-Z0-9_]+)$/', DB::connection()->getDatabaseName());
        $this->migrateTestDatabase();
    }

    public function test_giving_and_editing_a_pass_serialize_on_the_event(): void
    {
        [$event, , $engagement] = $this->context();
        $pass = $event->passTypes()->create(['name' => 'Original', 'max_assignments' => 1]);
        $results = $this->concurrently(
            fn () => app(PassAssignmentService::class)->give($engagement, $pass, 1),
            fn () => app(PassTypeService::class)->update($pass, ['name' => 'Updated', 'max_assignments' => 1], new Collection),
        );
        $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame(1, $pass->assignments()->count());
        $this->assertSame('Updated', $pass->fresh()->name);
    }

    public function test_consumption_and_stock_adjustment_serialize_without_lost_inventory(): void
    {
        [$event, $user, $engagement] = $this->context();
        $this->grantAdminAccess($user);
        $engagement->people()->attach($user->person_id);
        $location = $event->locations()->create(['name' => 'Gate']);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $this->seedHeldStock($item, $location, 2, $user);
        $pass = $event->passTypes()->create(['name' => 'Artist']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $user->person_id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $results = $this->concurrently(
            fn () => app(EntitlementConsumeService::class)->consume($expected, $user),
            fn () => app(EntitlementItemService::class)->adjust($item, $location->id, 3, null, $user),
        );
        $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame(3, app(EntitlementItemService::class)->balanceForLocation($item, $location->id));
        $this->assertSame(1, $expected->issuedEntitlement()->count());
        app(EventService::class)->delete($event);
    }

    public function test_duplicate_consumption_is_a_controlled_conflict(): void
    {
        [$event, $user, $engagement] = $this->context();
        $this->grantAdminAccess($user);
        $engagement->people()->attach($user->person_id);
        $location = $event->locations()->create(['name' => 'Gate']);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $this->seedHeldStock($item, $location, 2, $user);
        $pass = $event->passTypes()->create(['name' => 'Artist']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $user->person_id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $results = $this->concurrently(
            fn () => app(EntitlementConsumeService::class)->consume($expected, $user),
            fn () => app(EntitlementConsumeService::class)->consume($expected, $user),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(1, $expected->issuedEntitlement()->count());
        $this->assertSame(0, app(EntitlementItemService::class)->balanceForLocation($item, $location->id));
        app(EventService::class)->delete($event);
    }

    public function test_settings_update_observes_an_event_lock_committed_after_model_loading(): void
    {
        [$event] = $this->context();
        $results = $this->concurrently(
            fn () => app(EventService::class)->lock($event),
            fn () => app(EventService::class)->update($event, ['name' => 'Too Late']),
        );
        $this->assertSame(['committed', 'http_403'], array_column($results, 'status'), json_encode($results));
        $this->assertTrue($event->fresh()->isLocked());
        $this->assertSame('Concurrent Event', $event->fresh()->name);
    }

    public function test_concurrent_duplicate_contacts_preserve_one_primary_link(): void
    {
        [, , $engagement] = $this->context();
        $data = ['name' => 'Contact', 'email' => 'contact@example.test'];
        $results = $this->concurrently(
            fn () => app(EngagementPersonService::class)->store($engagement, $data),
            fn () => app(EngagementPersonService::class)->store($engagement, $data),
        );
        $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(1, $engagement->people()->count());
        $this->assertTrue((bool) $engagement->people()->firstOrFail()->pivot->is_primary);
    }

    public function test_global_and_event_team_updates_lock_events_before_people(): void
    {
        [$event, $actor] = $this->context();
        $this->grantAdminAccess($actor);
        $person = Person::create(['name' => 'Original', 'email' => 'member@example.test']);
        $member = TeamEngagement::create(['event_id' => $event->id, 'person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $results = $this->concurrently(
            fn () => app(TeamEngagementService::class)->update($member, $actor, [
                'name' => 'Original', 'email' => $person->email, 'status' => 'hired', 'employment_type' => 'volunteer',
            ]),
            fn () => app(GlobalTeamService::class)->update($person, [
                'name' => 'Global Edit', 'can_log_in' => false,
                'event_access' => [['event_id' => $event->id, 'role_id' => null]],
            ], $actor),
        );
        $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame('Global Edit', $person->fresh()->name);
    }

    public function test_inventory_pulls_competing_for_last_units_are_atomic(): void
    {
        [$event, $actor] = $this->context();
        $this->grantAdminAccess($actor);
        $location = $event->locations()->create(['name' => 'Stock gate']);
        $item = $event->entitlementItems()->create(['name' => 'Last pull']);
        $this->seedStock($item, $location, 1, $actor);
        $data = ['location_id' => $location->id, 'entitlement_item_id' => $item->id,
            'person_id' => $actor->person_id, 'quantity' => 1];
        $results = $this->concurrently(
            fn () => app(InventoryMovementService::class)->pull($event, $actor, $data),
            fn () => app(InventoryMovementService::class)->pull($event, $actor, $data),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(['events', 'events'], array_column($results, 'first_lock'));
        $this->assertSame(1, $item->movements()->where('type', 'pull')->count());
        $this->assertSame(0, app(StockMovementService::class)->balance($item, StockHolder::where('location_id', $location->id)->sole()));
    }

    public function test_inventory_returns_competing_for_last_unit_are_atomic(): void
    {
        [$event, $actor] = $this->context();
        $this->grantAdminAccess($actor);
        $location = $event->locations()->create(['name' => 'Stock gate']);
        $item = $event->entitlementItems()->create(['name' => 'Last return']);
        $this->seedStock($item, $location, 1, $actor);
        $data = ['location_id' => $location->id, 'entitlement_item_id' => $item->id,
            'person_id' => $actor->person_id, 'quantity' => 1];
        app(InventoryMovementService::class)->pull($event, $actor, $data);
        unset($data['person_id']);
        $results = $this->concurrently(
            fn () => app(InventoryMovementService::class)->returnStock($event, $actor, $data),
            fn () => app(InventoryMovementService::class)->returnStock($event, $actor, $data),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(1, $item->movements()->where('type', 'return')->count());
        $this->assertSame(0, app(StockMovementService::class)->balance($item, StockHolder::where('person_id', $actor->person_id)->sole()));
    }

    public function test_inventory_pull_prevents_concurrent_unhiring_and_unhiring_prevents_a_later_pull(): void
    {
        foreach ([true, false] as $pullFirst) {
            [$event, $actor] = $this->context();
            $this->grantAdminAccess($actor);
            $recipient = User::factory()->create();
            $role = Role::create(['name' => 'Receiver '.$recipient->id, 'permissions' => ['checkin.edit']]);
            $member = TeamEngagement::create(['event_id' => $event->id, 'person_id' => $recipient->person_id,
                'role_id' => $role->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
            $location = $event->locations()->create(['name' => 'Gate']);
            $item = $event->entitlementItems()->create(['name' => 'Wristband']);
            $this->seedStock($item, $location, 1, $actor);
            $data = ['location_id' => $location->id, 'entitlement_item_id' => $item->id, 'person_id' => $recipient->person_id, 'quantity' => 1];
            $pull = fn () => app(InventoryMovementService::class)->pull($event, $actor, $data);
            $remove = fn () => app(TeamEngagementService::class)->updateStatus($member, 'declined');
            $results = $this->concurrently($pullFirst ? $pull : $remove, $pullFirst ? $remove : $pull);
            $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
            $this->assertSame($pullFirst ? 'hired' : 'declined', $member->fresh()->status);
            $this->assertSame($pullFirst ? 1 : 0, $item->movements()->where('type', 'pull')->count());
        }
    }

    public function test_inventory_pull_observes_role_revocation_committed_while_waiting(): void
    {
        [$event, $actor] = $this->context();
        $this->grantAdminAccess($actor);
        $recipient = User::factory()->create();
        $role = Role::create(['name' => 'Receiver', 'permissions' => ['checkin.edit']]);
        TeamEngagement::create(['event_id' => $event->id, 'person_id' => $recipient->person_id,
            'role_id' => $role->id, 'status' => 'hired']);
        $location = $event->locations()->create(['name' => 'Gate']);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $this->seedStock($item, $location, 1, $actor);
        $data = ['location_id' => $location->id, 'entitlement_item_id' => $item->id, 'person_id' => $recipient->person_id, 'quantity' => 1];
        $results = $this->concurrently(
            fn () => DB::transaction(function () use ($event, $role): void {
                Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                app(RoleService::class)->setActive($role, false);
            }),
            fn () => app(InventoryMovementService::class)->pull($event, $actor, $data),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(0, $item->movements()->where('type', 'pull')->count());
    }

    public function test_inventory_pull_and_global_access_removal_cannot_strand_stock(): void
    {
        foreach (['admin', 'event_role'] as $kind) {
            foreach ([true, false] as $pullFirst) {
                [$event, $actor] = $this->context();
                $this->grantAdminAccess($actor);
                $recipient = User::factory()->create();
                $roleId = null;
                if ($kind === 'admin') {
                    $this->grantAdminAccess($recipient);
                } else {
                    $roleId = Role::create(['name' => 'Receiver '.$recipient->id, 'permissions' => ['checkin.edit']])->id;
                    TeamEngagement::create(['event_id' => $event->id, 'person_id' => $recipient->person_id,
                        'role_id' => $roleId, 'status' => 'hired']);
                }
                $location = $event->locations()->create(['name' => 'Gate']);
                $item = $event->entitlementItems()->create(['name' => 'Wristband']);
                $this->seedStock($item, $location, 1, $actor);
                $pullData = ['location_id' => $location->id, 'entitlement_item_id' => $item->id,
                    'person_id' => $recipient->person_id, 'quantity' => 1];
                $change = ['name' => $recipient->name, 'can_log_in' => true,
                    'event_access' => [['event_id' => $event->id, 'role_id' => null]],
                    ...($kind === 'admin' ? ['is_admin' => false] : [])];
                $pull = fn () => app(InventoryMovementService::class)->pull($event, $actor, $pullData);
                $remove = fn () => app(GlobalTeamService::class)->update($recipient->person, $change, $actor);
                $results = $this->concurrently($pullFirst ? $pull : $remove, $pullFirst ? $remove : $pull);
                $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
                $this->assertSame($pullFirst ? 1 : 0, $item->movements()->where('type', 'pull')->count());
                if ($kind === 'admin') {
                    $this->assertSame($pullFirst, $recipient->fresh()->is_admin);
                } else {
                    $this->assertSame($pullFirst ? $roleId : null, TeamEngagement::where('event_id', $event->id)
                        ->where('person_id', $recipient->person_id)->sole()->role_id);
                }
            }
        }
    }

    public function test_global_team_saves_do_not_wait_on_unsubmitted_events_when_admin_is_unchanged(): void
    {
        foreach ([null, false, true] as $adminFlag) {
            [$event, $actor] = $this->context();
            $this->grantAdminAccess($actor);
            $person = User::factory()->create()->person;
            if ($adminFlag === true) {
                $this->grantAdminAccess($person->user);
            }
            $unrelated = Event::create(['name' => 'Unrelated event', 'starts_on' => '2027-06-01',
                'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
            $data = ['name' => 'Updated name', 'can_log_in' => true,
                'event_access' => [['event_id' => $event->id, 'role_id' => null]],
                ...($adminFlag !== null ? ['is_admin' => $adminFlag] : [])];
            $results = $this->concurrently(
                fn () => DB::transaction(fn () => Event::whereKey($unrelated->id)->lockForUpdate()->firstOrFail()),
                function () use ($person, $data, $actor): void {
                    DB::statement("SET lock_timeout = '200ms'");
                    app(GlobalTeamService::class)->update($person, $data, $actor);
                },
            );
            $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
            $this->assertSame('Updated name', $person->fresh()->name);
        }
    }

    private function context(): array
    {
        $event = Event::create(['name' => 'Concurrent Event', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        $user = User::factory()->create();
        $engagement = ArtistEngagement::factory()->for($event)->create(['status' => 'confirmed']);

        return [$event, $user, $engagement];
    }

    public function test_inventory_pass_and_take_back_competing_for_last_person_unit_are_atomic(): void
    {
        [$event, $manager, $source, $target, $item, $holder] = $this->personMovementContext();
        $results = $this->concurrently(
            fn () => app(InventoryMovementService::class)->passStock($event, $source, [
                'person_id' => $target->person_id, 'entitlement_item_id' => $item->id, 'quantity' => 1,
            ]),
            fn () => app(InventoryMovementService::class)->takeBack($event, $manager, $holder, [
                'destination' => 'me', 'entitlement_item_id' => $item->id, 'quantity' => 1,
            ]),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(['events', 'events'], array_column($results, 'first_lock'));
        $this->assertSame([true, true], array_column($results, 'holder_locked'));
        $this->assertSame(0, app(StockMovementService::class)->balance($item, $holder));
        $this->assertSame(1, $item->movements()->whereIn('type', ['transfer', 'take_back'])->count());
    }

    public function test_inventory_return_to_manager_and_pass_competing_for_last_unit_are_atomic(): void
    {
        [$event, $manager, $source, $target, $item, $holder] = $this->personMovementContext();
        $results = $this->concurrently(
            fn () => app(InventoryMovementService::class)->returnStock($event, $source, [
                'person_id' => $manager->person_id, 'entitlement_item_id' => $item->id, 'quantity' => 1,
            ]),
            fn () => app(InventoryMovementService::class)->passStock($event, $source, [
                'person_id' => $target->person_id, 'entitlement_item_id' => $item->id, 'quantity' => 1,
            ]),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(0, app(StockMovementService::class)->balance($item, $holder));
        $this->assertSame(1, $item->movements()->whereIn('type', ['return', 'transfer'])->count());
    }

    public function test_inventory_pass_rechecks_group_membership_after_waiting_for_event_lock(): void
    {
        [$event, , $source, $target, $item] = $this->personMovementContext();
        $member = TeamEngagement::where('event_id', $event->id)->where('person_id', $target->person_id)->sole();
        $results = $this->concurrently(
            fn () => DB::transaction(function () use ($event, $member): void {
                Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                $member->update(['group_id' => null]);
            }),
            fn () => app(InventoryMovementService::class)->passStock($event, $source, [
                'person_id' => $target->person_id, 'entitlement_item_id' => $item->id, 'quantity' => 1,
            ]),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(0, $item->movements()->where('type', 'transfer')->count());
    }

    public function test_inventory_manager_return_and_take_back_recheck_revoked_receiver_grants(): void
    {
        foreach (['return', 'take_back'] as $type) {
            [$event, $manager, $source, $target, $item, $holder] = $this->personMovementContext();
            $receiver = $type === 'return' ? $manager : $target;
            $role = TeamEngagement::where('event_id', $event->id)->where('person_id', $receiver->person_id)->sole()->role;
            $results = $this->concurrently(
                fn () => DB::transaction(function () use ($event, $role, $type): void {
                    Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                    app(RoleService::class)->update($role, $role->name, $type === 'return' ? ['checkin.edit'] : ['checkin.view']);
                }),
                fn () => $type === 'return'
                    ? app(InventoryMovementService::class)->returnStock($event, $source, ['person_id' => $receiver->person_id, 'entitlement_item_id' => $item->id, 'quantity' => 1])
                    : app(InventoryMovementService::class)->takeBack($event, $manager, $holder, ['destination' => 'person', 'person_id' => $receiver->person_id, 'entitlement_item_id' => $item->id, 'quantity' => 1]),
            );
            $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
            $this->assertSame(0, $item->movements()->where('type', $type)->count());
        }
    }

    private function personMovementContext(): array
    {
        [$event, $manager] = $this->context();
        $role = Role::create(['name' => 'Inventory manager '.$event->id, 'permissions' => ['inventory.manage']]);
        TeamEngagement::create(['event_id' => $event->id, 'person_id' => $manager->person_id, 'role_id' => $role->id, 'status' => 'hired']);
        $group = $event->groups()->create(['name' => 'Handover']);
        $source = User::factory()->create();
        $target = User::factory()->create();
        foreach ([$source, $target] as $user) {
            $memberRole = Role::create(['name' => 'Crew '.$user->id, 'permissions' => ['inventory.pass_group']]);
            TeamEngagement::create(['event_id' => $event->id, 'person_id' => $user->person_id, 'role_id' => $memberRole->id, 'status' => 'hired', 'group_id' => $group->id]);
        }
        $location = $event->locations()->create(['name' => 'Gate']);
        $item = $event->entitlementItems()->create(['name' => 'Last person unit']);
        $this->seedStock($item, $location, 1, $manager);
        app(InventoryMovementService::class)->pull($event, $manager, ['location_id' => $location->id, 'person_id' => $source->person_id, 'entitlement_item_id' => $item->id, 'quantity' => 1]);
        $holder = StockHolder::where('event_id', $event->id)->where('person_id', $source->person_id)->sole();

        return [$event, $manager, $source, $target, $item, $holder];
    }

    public function test_concurrent_meal_type_windows_return_a_validation_conflict(): void
    {
        [$event] = $this->context();
        $results = $this->concurrently(
            fn () => app(MealTypeService::class)->create($event, ['name' => 'Snack', 'starts_at' => '23:00', 'ends_at' => '01:00']),
            fn () => app(MealTypeService::class)->create($event, ['name' => 'Night snack', 'starts_at' => '00:30', 'ends_at' => '02:00']),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame(1, $event->mealTypes()->count());
    }

    public function test_concurrent_meal_type_names_return_a_validation_conflict(): void
    {
        [$event] = $this->context();
        $results = $this->concurrently(
            fn () => app(MealTypeService::class)->create($event, ['name' => 'Snack', 'starts_at' => '02:00', 'ends_at' => '03:00']),
            fn () => app(MealTypeService::class)->create($event, ['name' => 'SNACK', 'starts_at' => '04:00', 'ends_at' => '05:00']),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(1, $event->mealTypes()->count());
    }

    public function test_simultaneous_claims_of_one_meal_record_only_one_claim(): void
    {
        [$event, $user, $member, $meal, $shift] = $this->mealContext();
        $claim = function () use ($event, $user, $member, $meal, $shift): void {
            $result = app(MealClaimService::class)->claim($event, $member, $user, MealAssignment::query()->where('team_engagement_id', $member->id)->where('source_shift_id', $shift->id)->where('meal_id', $meal->id)->sole()->id);
            if ($result['status'] !== 'claimed') {
                throw ValidationException::withMessages(['meal_id' => $result['status']]);
            }
        };
        $results = $this->concurrently($claim, $claim);
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame(1, MealAssignment::query()->whereNotNull('claimed_at')->count());
    }

    public function test_claim_on_another_shift_observes_the_first_claim_and_requires_warning(): void
    {
        [$event, $user, $member, $meal, $shift] = $this->mealContext();
        $other = $event->shifts()->create(['name' => 'Second shift', 'location_id' => $shift->location_id,
            'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at]);
        $assignment = $other->assignments()->create(['team_engagement_id' => $member->id,
            'role_id' => $shift->assignments()->sole()->role_id, 'starts_at' => $other->starts_at, 'ends_at' => $other->ends_at]);
        app(MealAssignmentService::class)->syncShiftMeal($other->meals()->create(['meal_id' => $meal->id]), [$assignment->id]);
        $results = $this->concurrently(
            fn () => app(MealClaimService::class)->claim($event, $member, $user, MealAssignment::query()->where('team_engagement_id', $member->id)->where('source_shift_id', $shift->id)->where('meal_id', $meal->id)->sole()->id),
            function () use ($event, $member, $user, $meal, $other): void {
                $result = app(MealClaimService::class)->claim($event, $member, $user, MealAssignment::query()->where('team_engagement_id', $member->id)->where('source_shift_id', $other->id)->where('meal_id', $meal->id)->sole()->id);
                if ($result['status'] !== 'warning_required') {
                    throw new \RuntimeException('Expected same-type warning.');
                }
                throw ValidationException::withMessages(['meal_id' => $result['status']]);
            },
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(1, MealAssignment::query()->whereNotNull('claimed_at')->count());
    }

    public function test_claim_observes_an_event_locked_after_models_were_loaded(): void
    {
        [$event, $user, $member, $meal, $shift] = $this->mealContext();
        $results = $this->concurrently(
            fn () => app(EventService::class)->lock($event),
            fn () => app(MealClaimService::class)->claim($event, $member, $user, MealAssignment::query()->where('team_engagement_id', $member->id)->where('source_shift_id', $shift->id)->where('meal_id', $meal->id)->sole()->id),
        );
        $this->assertSame(['committed', 'http_403'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(0, MealAssignment::query()->whereNotNull('claimed_at')->count());
    }

    public function test_simultaneous_unclaims_correct_a_meal_only_once(): void
    {
        [$event, $user, $member, $meal, $shift] = $this->mealContext();
        $claimId = app(MealClaimService::class)->claim($event, $member, $user, MealAssignment::query()->where('team_engagement_id', $member->id)->where('source_shift_id', $shift->id)->where('meal_id', $meal->id)->sole()->id)['assignment_id'];
        $claimToken = MealAssignment::findOrFail($claimId)->claim_token;
        $unclaim = function () use ($event, $user, $member, $claimId, $claimToken): void {
            $result = app(MealClaimService::class)->unclaim($event, $member, $user, $claimId, $claimToken);
            if ($result['status'] !== 'unclaimed') {
                throw ValidationException::withMessages(['claim_id' => $result['status']]);
            }
        };
        $results = $this->concurrently($unclaim, $unclaim);
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame(0, MealAssignment::query()->whereNotNull('claimed_at')->count());
    }

    public function test_claim_waits_for_an_in_progress_unclaim_and_consumes_the_restored_grant(): void
    {
        [$event, $user, $member, $meal, $shift] = $this->mealContext();
        $claimId = app(MealClaimService::class)->claim($event, $member, $user, MealAssignment::query()->where('team_engagement_id', $member->id)->where('source_shift_id', $shift->id)->where('meal_id', $meal->id)->sole()->id)['assignment_id'];
        $claimToken = MealAssignment::findOrFail($claimId)->claim_token;
        $results = $this->concurrently(
            fn () => app(MealClaimService::class)->unclaim($event, $member, $user, $claimId, $claimToken),
            function () use ($event, $user, $member, $meal, $shift): void {
                $result = app(MealClaimService::class)->claim($event, $member, $user, MealAssignment::query()->where('team_engagement_id', $member->id)->where('source_shift_id', $shift->id)->where('meal_id', $meal->id)->sole()->id);
                if ($result['status'] !== 'claimed') {
                    throw new \RuntimeException('Expected the restored grant to be available.');
                }
            },
        );
        $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame(1, MealAssignment::query()->whereNotNull('claimed_at')->count());
        $this->assertNotSame($claimToken, MealAssignment::findOrFail($claimId)->claim_token);
        $this->assertSame('already_unclaimed', app(MealClaimService::class)->unclaim($event, $member, $user, $claimId, $claimToken)['status']);
        $this->assertSame(1, MealAssignment::query()->whereNotNull('claimed_at')->count());
    }

    public function test_override_waits_for_unclaim_and_refuses_the_restored_unused_grant(): void
    {
        [$event, $user, $member, $meal] = $this->mealContext();
        $id = MealAssignment::query()->sole()->id;
        $claim = app(MealClaimService::class)->claim($event, $member, $user, $id);
        $results = $this->concurrently(
            fn () => app(MealClaimService::class)->unclaim($event, $member, $user, $id, $claim['claim_token']),
            function () use ($event, $user, $member, $meal): void {
                $result = app(MealOverrideService::class)->give($event, $member, $user, $meal->id);
                if ($result['status'] === 'unused_meal') {
                    throw ValidationException::withMessages(['meal_id' => $result['message']]);
                }
            },
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertSame(0, MealAssignment::where('is_override', true)->count());
    }

    public function test_override_removal_waits_for_claim_and_never_deletes_a_claimed_grant(): void
    {
        [$event, $user, $member, $meal] = $this->mealContext();
        $normalId = MealAssignment::query()->sole()->id;
        app(MealClaimService::class)->claim($event, $member, $user, $normalId);
        $override = app(MealOverrideService::class)->give($event, $member, $user, $meal->id);
        app(MealClaimService::class)->unclaim($event, $member, $user, $override['assignment_id'], $override['claim_token']);
        $results = $this->concurrently(
            fn () => app(MealClaimService::class)->claim($event, $member, $user, $override['assignment_id'], true),
            function () use ($event, $user, $member, $override): void {
                $result = app(MealOverrideService::class)->remove($event, $member, $user, $override['assignment_id']);
                if ($result['status'] === 'still_used') {
                    throw ValidationException::withMessages(['assignment_id' => $result['message']]);
                }
            },
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame('events', $results[1]['first_lock']);
        $this->assertNotNull(MealAssignment::findOrFail($override['assignment_id'])->claimed_at);
        // Remove committed test fixtures before DatabaseMigrations attempts its guarded rollback.
        app(EventService::class)->delete($event);
    }

    public function test_team_consumption_waits_for_status_change_and_rechecks_hired_status(): void
    {
        [$event, $user, $member] = $this->mealContext();
        $pass = $event->passTypes()->create(['name' => 'Crew']);
        $item = $event->entitlementItems()->create(['name' => 'Crew wristband']);
        $location = $event->locations()->firstOrFail();
        $this->seedHeldStock($item, $location, 2, $user);
        $assignment = $member->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $member->person_id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $expected->load('passAssignment.teamEngagement');
        $results = $this->concurrently(
            fn () => app(TeamEngagementService::class)->updateStatus($member, 'reviewing'),
            fn () => app(EntitlementConsumeService::class)->consume($expected, $user),
        );
        $this->assertSame(['committed', 'http_404'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(0, $expected->issuedEntitlement()->count());
        $this->assertSame(0, app(EntitlementItemService::class)->balanceForLocation($item, $location->id));
        app(EventService::class)->delete($event);
    }

    public function test_two_moves_cannot_overdraw_a_persons_last_unit(): void
    {
        [$event, $actor] = $this->context();
        $stocks = app(StockMovementService::class);
        $item = $event->entitlementItems()->create(['name' => 'Last wristband']);
        $location = $stocks->forLocation($event, $event->locations()->create(['name' => 'Gate'])->id);
        $person = $stocks->forPerson($event, $actor->person);
        $recipient = $stocks->forPerson($event, Person::create(['name' => 'Receiver', 'email' => 'stock-race@example.test']));
        $stocks->move($item, StockMovementType::Add, 1, null, $location, $actor);
        $stocks->move($item, StockMovementType::Pull, 1, $location, $person, $actor);

        $results = $this->concurrently(
            fn () => app(StockMovementService::class)->move($item, StockMovementType::Return, 1, $person, $location, $actor),
            fn () => app(StockMovementService::class)->move($item, StockMovementType::Transfer, 1, $person, $recipient, $actor),
        );

        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame([true, true], array_column($results, 'holder_locked'));
        $this->assertSame(0, $stocks->balance($item, $person));
        $this->assertSame(0, $stocks->balance($item, $recipient));
        $this->assertSame(1, $stocks->balance($item, $location));
        $this->assertSame(1, $stocks->total($item));
    }

    public function test_two_entitlements_compete_for_the_last_person_unit(): void
    {
        [$event, $actor, $engagement] = $this->context();
        $this->grantAdminAccess($actor);
        $engagement->people()->attach($actor->person_id);
        $location = $event->locations()->create(['name' => 'Gate']);
        $item = $event->entitlementItems()->create(['name' => 'Last wristband']);
        $this->seedHeldStock($item, $location, 1, $actor);
        $pass = $event->passTypes()->create(['name' => 'Artist']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $actor->person_id]);
        $first = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $second = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);

        $results = $this->concurrently(
            fn () => app(EntitlementConsumeService::class)->consume($first, $actor),
            fn () => app(EntitlementConsumeService::class)->consume($second, $actor),
        );

        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertSame([true, true], array_column($results, 'holder_locked'));
        $this->assertSame(1, $first->issuedEntitlement()->count());
        $this->assertSame(0, $second->issuedEntitlement()->count());
        $this->assertSame('expected', $second->fresh()->status);
        $this->assertSame(1, $item->movements()->where('type', StockMovementType::GiveOut)->count());
        $this->assertSame(0, app(StockMovementService::class)->total($item));
        app(EventService::class)->delete($event);
    }

    public function test_same_code_on_different_items_can_only_be_consumed_once(): void
    {
        [$event, $actor, $engagement] = $this->context();
        $this->grantAdminAccess($actor);
        $engagement->people()->attach($actor->person_id);
        $location = $event->locations()->create(['name' => 'Code gate']);
        $items = collect(['Wristband', 'Lanyard'])->map(fn ($name) => $event->entitlementItems()->create([
            'name' => $name, 'needs_unique_code' => true,
        ]));
        $pass = $event->passTypes()->create(['name' => 'Code pass']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $actor->person_id]);
        $expected = $items->map(function ($item) use ($location, $assignment, $actor) {
            $this->seedHeldStock($item, $location, 2, $actor);

            return $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        });
        $results = $this->concurrently(
            fn () => app(EntitlementConsumeService::class)->consume($expected[0], $actor, 'RACE-42'),
            fn () => app(EntitlementConsumeService::class)->consume($expected[1], $actor, ' race-42 '),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertDatabaseCount('entitlement_code_reservations', 1);
        $this->assertDatabaseCount('issued_entitlements', 1);
        $this->assertSame(1, DB::table('stock_movements')->where('event_id', $event->id)->where('type', StockMovementType::GiveOut->value)->count());
        $this->assertSame('expected', $expected[1]->fresh()->status);
        $this->assertSame(2, app(StockMovementService::class)->total($items[1]));
        app(EventService::class)->delete($event);
    }

    public function test_enabling_unique_codes_during_consume_enforces_the_new_setting(): void
    {
        [$event, $actor, $engagement] = $this->context();
        $this->grantAdminAccess($actor);
        $engagement->people()->attach($actor->person_id);
        $location = $event->locations()->create(['name' => 'Code gate']);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $this->seedHeldStock($item, $location, 2, $actor);
        $pass = $event->passTypes()->create(['name' => 'Code pass']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $actor->person_id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $expected->load('entitlementItem');
        $results = $this->concurrently(
            fn () => app(EntitlementItemService::class)->update($item, ['name' => $item->name, 'needs_unique_code' => true]),
            fn () => app(EntitlementConsumeService::class)->consume($expected, $actor),
        );
        $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
        $this->assertDatabaseCount('issued_entitlements', 0);
        $this->assertDatabaseCount('entitlement_code_reservations', 0);
        $this->assertSame(2, app(StockMovementService::class)->total($item));
    }

    public function test_concurrent_first_use_creates_only_one_person_holder(): void
    {
        [$event, $actor] = $this->context();
        $results = $this->concurrently(
            fn () => app(StockMovementService::class)->forPerson($event, $actor->person),
            fn () => app(StockMovementService::class)->forPerson($event, $actor->person),
        );
        $this->assertSame(['committed', 'committed'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(1, StockHolder::where('event_id', $event->id)->where('person_id', $actor->person_id)->count());
    }

    public function test_consume_and_return_competing_for_last_unit_are_atomic_in_both_orders(): void
    {
        foreach ([true, false] as $consumeFirst) {
            [$event, $actor, $engagement] = $this->context();
            $this->grantAdminAccess($actor);
            $engagement->people()->attach($actor->person_id);
            $location = $event->locations()->create(['name' => 'Gate']);
            $item = $event->entitlementItems()->create(['name' => 'Last wristband']);
            $holder = $this->seedHeldStock($item, $location, 1, $actor);
            $pass = $event->passTypes()->create(['name' => 'Artist']);
            $assignment = $engagement->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $actor->person_id]);
            $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
            $consume = fn () => app(EntitlementConsumeService::class)->consume($expected, $actor);
            $return = fn () => app(InventoryMovementService::class)->returnStock($event, $actor, [
                'entitlement_item_id' => $item->id, 'location_id' => $location->id, 'quantity' => 1,
            ]);
            $results = $this->concurrently($consumeFirst ? $consume : $return, $consumeFirst ? $return : $consume);
            $this->assertSame(['committed', 'validation'], array_column($results, 'status'), json_encode($results));
            $this->assertSame([true, true], array_column($results, 'holder_locked'));
            $this->assertSame(0, app(StockMovementService::class)->balance($item, $holder));
            $this->assertSame($consumeFirst ? 1 : 0, $expected->issuedEntitlement()->count());
            $this->assertSame($consumeFirst ? 'consumed' : 'expected', $expected->fresh()->status);
            app(EventService::class)->delete($event);
        }
    }

    public function test_consume_rechecks_permissions_revoked_while_waiting_for_event_lock(): void
    {
        [$event, $actor, $engagement] = $this->context();
        $this->grantAdminAccess($actor);
        $engagement->people()->attach($actor->person_id);
        $this->grantRoleAccess($actor, ['checkin.edit']);
        $role = $event->teamEngagements()->where('person_id', $actor->person_id)->sole()->role;
        $location = $event->locations()->create(['name' => 'Gate']);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $holder = $this->seedHeldStock($item, $location, 1, $actor);
        $pass = $event->passTypes()->create(['name' => 'Artist']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $actor->person_id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
        $results = $this->concurrently(
            fn () => DB::transaction(function () use ($event, $role): void {
                Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                app(RoleService::class)->update($role, $role->name, ['checkin.view']);
            }),
            fn () => app(EntitlementConsumeService::class)->consume($expected, $actor),
        );
        $this->assertSame(['committed', 'http_403'], array_column($results, 'status'), json_encode($results));
        $this->assertSame(1, app(StockMovementService::class)->balance($item, $holder));
        $this->assertSame(0, $expected->issuedEntitlement()->count());
        $this->assertSame('expected', $expected->fresh()->status);
        app(EventService::class)->delete($event);
    }

    private function mealContext(): array
    {
        $event = app(EventService::class)->create(['name' => 'Meal race', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'timezone' => 'UTC']);
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        $person = Person::create(['name' => 'Meal recipient', 'email' => 'meal@example.test']);
        $member = $event->teamEngagements()->create(['person_id' => $person->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $location = $event->locations()->create(['name' => 'Kitchen']);
        $shift = $event->shifts()->create(['location_id' => $location->id, 'name' => 'Lunch shift', 'starts_at' => '2026-10-01 09:00', 'ends_at' => '2026-10-01 18:00']);
        $role = Role::create(['name' => 'Meal crew']);
        $assignment = $shift->assignments()->create(['team_engagement_id' => $member->id, 'role_id' => $role->id,
            'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at]);
        $meal = $event->meals()->create(['name' => 'Lunch', 'meal_type_id' => $event->mealTypes()->where('name', 'Lunch')->sole()->id,
            'date' => '2026-10-01', 'starts_at' => '12:00:00', 'ends_at' => '14:00:00']);
        app(MealAssignmentService::class)->syncShiftMeal($shift->meals()->create(['meal_id' => $meal->id]), [$assignment->id]);
        $this->travelTo(Carbon::parse('2026-10-01 12:00', 'UTC'));

        return [$event, $user, $member, $meal, $shift];
    }

    /** Run real service transactions on committed fixtures and independent connections. */
    private function concurrently(callable $first, callable $second): array
    {
        $barrier = tempnam(sys_get_temp_dir(), 'ez-db-concurrency-');
        $this->assertNotFalse($barrier);
        DB::purge();
        $pids = [];

        try {
            foreach ([$first, $second] as $index => $operation) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);
                if ($pid === 0) {
                    $result = ['status' => 'error', 'first_lock' => null, 'holder_locked' => false];
                    try {
                        DB::statement("SET statement_timeout = '8s'");
                        if ($index === 1) {
                            $deadline = microtime(true) + 5;
                            while (! is_file($barrier.'.first') && microtime(true) < $deadline) {
                                usleep(10000);
                                clearstatcache();
                            }
                            if (! is_file($barrier.'.first')) {
                                throw new \RuntimeException('First worker did not acquire the event lock.');
                            }
                        }
                        DB::listen(function ($query) use ($index, $barrier, &$result): void {
                            if (! str_contains(strtolower($query->sql), 'for update')) {
                                return;
                            }
                            if (str_contains($query->sql, 'from "stock_holders"')) {
                                $result['holder_locked'] = true;
                            }
                            if ($result['first_lock'] === null && preg_match('/from "([^"]+)"/', $query->sql, $matches)) {
                                $result['first_lock'] = $matches[1];
                                if ($index === 1) {
                                    touch($barrier.'.second');
                                }
                            }
                            if ($index === 0 && str_contains($query->sql, 'from "events"') && ! is_file($barrier.'.first')) {
                                touch($barrier.'.first');
                                // With correct ordering worker two waits on this event.
                                // With inverted ordering it holds its child row first.
                                $deadline = microtime(true) + 0.4;
                                while (! is_file($barrier.'.second') && microtime(true) < $deadline) {
                                    usleep(10000);
                                    clearstatcache();
                                }
                            }
                        });
                        $operation();
                        $result['status'] = 'committed';
                    } catch (ValidationException) {
                        $result['status'] = 'validation';
                    } catch (HttpException $exception) {
                        $result['status'] = 'http_'.$exception->getStatusCode();
                    } catch (\Throwable $exception) {
                        $result['error'] = get_class($exception).': '.$exception->getMessage();
                    }
                    file_put_contents($barrier.'.result.'.$index, json_encode($result, JSON_THROW_ON_ERROR));
                    DB::disconnect();
                    exit(0);
                }
                $pids[] = $pid;
            }
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0);
            }

            return array_map(fn (int $index) => json_decode(file_get_contents($barrier.'.result.'.$index), true, flags: JSON_THROW_ON_ERROR), [0, 1]);
        } finally {
            foreach (glob($barrier.'*') as $path) {
                unlink($path);
            }
        }
    }
}
