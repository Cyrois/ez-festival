<?php

namespace Tests\Feature;

use App\Enums\StockMovement as MovementType;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\Location;
use App\Models\Role;
use App\Models\StockHolder;
use App\Models\StockMovement;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\GlobalTeamService;
use App\Services\HeldStockGuard;
use App\Services\InventoryMovementService;
use App\Services\StockMovementService;
use App\Services\TeamEngagementService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckInInventoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Event $event;

    private User $manager;

    private User $receiver;

    private Location $location;

    private EntitlementItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = $this->newEvent('Festival');
        $this->manager = $this->member(['inventory.manage']);
        $this->receiver = $this->member(['checkin.edit']);
        $this->location = $this->event->locations()->create(['name' => 'Gate']);
        $this->item = $this->event->entitlementItems()->create(['name' => 'Wristband']);
        $this->seedStock($this->item, $this->location, 10, $this->manager);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->actingAs($this->manager);
    }

    public function test_inventory_permission_matrix_and_empty_reads_do_not_create_holders(): void
    {
        foreach (['checkin.edit', 'inventory.pass_shift', 'inventory.pass_group', 'inventory.manage'] as $key) {
            $viewer = $this->member([$key]);
            $this->actingAs($viewer)->get(route('check-in.index'))->assertInertia(fn (Assert $p) => $p->where('permissions', fn ($permissions) => $permissions['checkin.edit']));
            $this->get(route('check-in.my-stock'))->assertInertia(fn (Assert $p) => $p
                ->component('CheckIn/Inventory')->where('view', 'mine')->where('canManage', $key === 'inventory.manage')->has('ownItems', 0));
            if ($key === 'inventory.manage') {
                $this->get(route('check-in.inventory.index'))->assertInertia(fn (Assert $p) => $p
                    ->component('CheckIn/Inventory')->where('view', 'holdings'));
            } else {
                $this->get(route('check-in.inventory.index'))->assertForbidden();
            }
            $this->getJson(route('check-in.inventory.stock'))->assertOk()->assertJsonCount(0, 'data');
            $this->getJson(route('check-in.inventory.movements'))->assertOk()->assertJsonCount(0, 'data');
            $this->assertDatabaseMissing('stock_holders', ['person_id' => $viewer->person_id]);
        }
        $viewer = $this->member(['checkin.view']);
        $this->actingAs($viewer)->get(route('check-in.index'))->assertInertia(fn (Assert $p) => $p->where('permissions', fn ($permissions) => ! $permissions['checkin.edit']));
        $this->get(route('check-in.my-stock'))->assertForbidden();
        $this->get(route('check-in.inventory.index'))->assertForbidden();
    }

    public function test_http_pull_allows_self_and_admin_without_team_records_and_records_actor(): void
    {
        $admin = $this->grantAdminAccess(User::factory()->create());
        foreach ([$this->receiver, $this->manager, $admin] as $receiver) {
            $this->post(route('check-in.inventory.pulls.store', $this->event), $this->pullData($receiver, 2))
                ->assertRedirect()->assertSessionHasNoErrors();
            $movement = StockMovement::latest('id')->first();
            $this->assertSame(MovementType::Pull, $movement->type);
            $this->assertSame($this->manager->id, $movement->actor_user_id);
            $this->assertSame($receiver->person_id, $movement->toHolder->person_id);
            $this->assertSame(2, $this->balance($receiver));
        }
        $this->assertSame(4, app(StockMovementService::class)->balance($this->item, StockHolder::where('location_id', $this->location->id)->sole()));
        $this->assertDatabaseCount('stock_movements', 4);
    }

    public function test_own_reads_do_not_expose_other_balances_or_allow_managing_endpoints(): void
    {
        $this->pull($this->receiver, 3);
        $this->pull($this->manager, 2);
        $otherHolder = StockHolder::where('person_id', $this->manager->person_id)->sole();
        $this->actingAs($this->receiver)->get(route('check-in.my-stock'))->assertInertia(fn (Assert $p) => $p
            ->where('view', 'mine')->where('canManage', false)->has('ownItems', 1)->where('ownItems.0.balance', 3)->missing('holdings'));
        $this->getJson(route('check-in.inventory.stock', ['holder_id' => $otherHolder->id]))
            ->assertOk()->assertJsonPath('data.0.balance', 3);
        $this->getJson(route('check-in.inventory.movements'))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.receiver', $this->receiver->name)
            ->assertJsonPath('data.0.recorded_by', $this->manager->name);
        foreach (['holdings', 'receivers', 'availability'] as $route) {
            $this->getJson(route('check-in.inventory.'.$route))->assertForbidden();
        }
        foreach (['stock'] as $route) {
            $this->getJson(route('check-in.inventory.holders.'.$route, $otherHolder))->assertForbidden();
        }
        $this->post(route('check-in.inventory.pulls.store', $this->event), $this->pullData())->assertForbidden();
    }

    public function test_holdings_filters_keep_all_item_totals_locations_first_and_exclude_empty_holders(): void
    {
        $this->pull($this->receiver, 3);
        $second = $this->event->entitlementItems()->create(['name' => 'Voucher']);
        $this->seedStock($second, $this->location, 4);
        app(StockMovementService::class)->move($second, MovementType::Pull, 4,
            StockHolder::where('location_id', $this->location->id)->sole(),
            StockHolder::where('person_id', $this->receiver->person_id)->sole(), $this->manager);
        app(StockMovementService::class)->forPerson($this->event, $this->manager->person);
        $this->getJson(route('check-in.inventory.holdings'))->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.holder_type', 'location')->assertJsonPath('data.1.total', 7);
        $this->getJson(route('check-in.inventory.holdings', ['item' => $this->item->id, 'holder_type' => 'person']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.total', 7)->assertJsonCount(2, 'data.0.items')
            ->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.holder_type', 'person');
        $this->getJson(route('check-in.inventory.holdings', ['holder_type' => 'location']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.holder_type', 'location')
            ->assertJsonPath('recordsTotal', 1);
        $this->getJson(route('check-in.inventory.holdings', ['holder_type' => 'person', 'search' => 'Not found']))
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('recordsTotal', 1)->assertJsonPath('recordsFiltered', 0);
        $this->receiver->person()->update(['name' => '100%_name!']);
        $this->getJson(route('check-in.inventory.holdings', ['search' => '%_']))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(route('check-in.inventory.holdings', ['search' => 'Not found']))->assertJsonCount(0, 'data');
    }

    public function test_holdings_and_receiver_search_are_paged_and_send_names_without_personal_details(): void
    {
        for ($i = 0; $i < 28; $i++) {
            $user = $this->member(['inventory.pass_group']);
            $user->person()->update(['name' => sprintf('Receiver %02d', $i)]);
            $this->seedStock($this->item, $this->location, 1);
            $this->pull($user, 1);
        }
        $this->getJson(route('check-in.inventory.holdings', ['start' => 25, 'length' => 10]))
            ->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('recordsTotal', 29);
        $this->getJson(route('check-in.inventory.holdings', ['holder_type' => 'person', 'start' => 25, 'length' => 10]))
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('recordsTotal', 28);
        $this->getJson(route('check-in.inventory.holdings', ['holder_type' => 'location', 'start' => 0, 'length' => 25]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('recordsTotal', 1);
        $response = $this->getJson(route('check-in.inventory.receivers', ['search' => 'Receiver', 'page' => 2]))
            ->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame(['id', 'name'], array_keys($response->json('data.0')));
    }

    public function test_partial_and_full_return_use_only_actor_stock_and_allow_empty_destination(): void
    {
        $this->pull($this->receiver, 5);
        $destination = $this->event->locations()->create(['name' => 'Returns']);
        $this->actingAs($this->receiver);
        foreach ([2, 3] as $quantity) {
            $data = $this->pullData($this->manager, $quantity);
            $data['location_id'] = $destination->id;
            unset($data['person_id']);
            $this->put(route('check-in.inventory.returns.update', $this->event), $data)->assertSessionHasNoErrors()->assertRedirect();
            $movement = StockMovement::latest('id')->first();
            $this->assertSame(MovementType::Return, $movement->type);
            $this->assertSame($this->receiver->person_id, $movement->fromHolder->person_id);
            $this->assertSame($destination->id, $movement->toHolder->location_id);
        }
        $this->assertSame(0, $this->balance($this->receiver));
        $this->put(route('check-in.inventory.returns.update', $this->event), $data)->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('stock_movements', 4);
    }

    public function test_invalid_quantities_and_foreign_or_ineligible_ids_leave_ledger_unchanged(): void
    {
        foreach ([0, -1, 1.5, 'abc', '2147483648', null] as $quantity) {
            $this->post(route('check-in.inventory.pulls.store', $this->event), $this->pullData(quantity: $quantity))
                ->assertSessionHasErrors('quantity');
        }
        $this->post(route('check-in.inventory.pulls.store', $this->event), $this->pullData(quantity: 11))->assertSessionHasErrors('quantity');
        $foreign = $this->newEvent('Other');
        $foreignLocation = $foreign->locations()->create(['name' => 'Other Gate']);
        $foreignItem = $foreign->entitlementItems()->create(['name' => 'Other item']);
        foreach (['location_id' => $foreignLocation->id, 'entitlement_item_id' => $foreignItem->id] as $field => $id) {
            $data = $this->pullData();
            $data[$field] = $id;
            $this->post(route('check-in.inventory.pulls.store', $this->event), $data)->assertSessionHasErrors($field);
        }
        $this->post(route('check-in.inventory.pulls.store', $foreign), $this->pullData())->assertNotFound();
        foreach (['view', 'not_hired', 'role_off', 'login_off', 'no_account', 'foreign_event'] as $type) {
            $user = $this->member($type === 'view' ? ['checkin.view'] : ['checkin.edit']);
            $member = $this->teamMember($user);
            match ($type) {
                'not_hired' => $member->update(['status' => 'applied']),
                'role_off' => $member->role->update(['active' => false]),
                'login_off' => $user->person()->update(['can_log_in' => false]),
                'no_account' => $user->delete(),
                'foreign_event' => $member->update(['event_id' => $foreign->id, 'group_id' => $this->fixtureGroupId($foreign->id)]),
                default => null,
            };
            $this->post(route('check-in.inventory.pulls.store', $this->event), $this->pullData($user))->assertSessionHasErrors('person_id');
        }
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('stock_holders', 1);
    }

    public function test_role_revocation_flags_stock_and_refuses_own_movement_without_access_exception(): void
    {
        $this->pull($this->receiver, 3);
        $this->teamMember($this->receiver)->role->update(['permissions' => ['checkin.view']]);
        $this->getJson(route('check-in.inventory.holdings'))->assertOk()->assertJsonPath('data.1.inactive', true);
        $this->actingAs($this->receiver)->put(route('check-in.inventory.returns.update', $this->event), $this->pullData())->assertForbidden();
        $this->get(route('check-in.my-stock'))->assertForbidden();
        $this->get(route('check-in.inventory.index'))->assertForbidden();
        $this->assertSame(3, $this->balance($this->receiver));
    }

    public function test_locked_reads_do_not_write_and_all_movements_refuse_even_with_stale_models(): void
    {
        $this->pull($this->receiver, 1);
        $this->event->lock();
        $this->get(route('check-in.inventory.index'))->assertOk();
        $this->getJson(route('check-in.inventory.holdings'))->assertOk();
        $this->postJson(route('check-in.inventory.pulls.store', $this->event), $this->pullData())->assertForbidden();
        $this->putJson(route('check-in.inventory.returns.update', $this->event), $this->pullData())->assertForbidden();
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_holder_detail_and_availability_are_scoped_and_history_is_paged_newest_first(): void
    {
        $this->pull($this->receiver, 2);
        $holder = StockHolder::where('person_id', $this->receiver->person_id)->sole();
        $this->getJson(route('check-in.inventory.holders.stock', $holder))->assertOk()->assertJsonPath('data.0.balance', 2);
        $this->getJson(route('check-in.history.data', ['holder' => 'person:'.$this->receiver->person_id]))->assertOk()->assertJsonPath('data.0.type', 'pull');
        $foreign = $this->newEvent('Other');
        $foreignHolder = app(StockMovementService::class)->forPerson($foreign, $this->receiver->person);
        $this->getJson(route('check-in.inventory.holders.stock', $foreignHolder))->assertNotFound();
        $this->getJson(route('check-in.inventory.availability'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.balance', 8);
        $return = $this->pullData(quantity: 1);
        unset($return['person_id']);
        $this->actingAs($this->receiver)->put(route('check-in.inventory.returns.update', $this->event), $return)->assertSessionHasNoErrors();
        $this->getJson(route('check-in.inventory.movements', ['start' => 0, 'length' => 1]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('recordsTotal', 2)->assertJsonPath('data.0.type', 'return');
    }

    public function test_team_status_role_and_person_rebinding_paths_are_blocked_by_stock(): void
    {
        $this->pull($this->receiver, 2);
        $this->grantAdminAccess($this->manager);
        $member = $this->teamMember($this->receiver);
        $this->patch(route('team.members.status.update', $member), ['status' => 'declined'])->assertSessionHasErrors('status');
        $data = ['name' => $this->receiver->name, 'email' => $this->receiver->email,
            'status' => 'hired', 'employment_type' => 'volunteer', 'group_id' => $member->group_id];
        foreach (['status' => 'declined', 'role_id' => null, 'email' => 'replacement@example.test'] as $field => $value) {
            $this->put(route('team.members.update', $member), [...$data, $field => $value])->assertSessionHasErrors($field);
        }
        try {
            app(TeamEngagementService::class)->updateStatus($member, 'declined');
            $this->fail('Service must guard held stock.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('status', $error->errors());
        }
        $this->assertSame('hired', $member->fresh()->status);
        $this->assertSame($this->receiver->person_id, $member->fresh()->person_id);
    }

    public function test_global_event_access_and_all_event_admin_removal_are_blocked_until_stock_is_cleared(): void
    {
        $this->pull($this->receiver, 2);
        $this->grantAdminAccess($this->manager);
        $member = $this->teamMember($this->receiver);
        $data = ['name' => $this->receiver->name, 'can_log_in' => true,
            'event_access' => [['event_id' => $this->event->id, 'role_id' => null]]];
        $this->put(route('settings.team.update', $this->receiver->person), $data)->assertSessionHasErrors('event_access.0.role_id');
        $this->grantAdminAccess($this->receiver);
        $other = $this->newEvent('Other');
        $otherLocation = $other->locations()->create(['name' => 'Other Gate']);
        $otherItem = $other->entitlementItems()->create(['name' => 'Other item']);
        $this->seedStock($otherItem, $otherLocation, 1);
        $otherHolder = app(StockMovementService::class)->forPerson($other, $this->receiver->person);
        app(StockMovementService::class)->move($otherItem, MovementType::Pull, 1,
            StockHolder::where('location_id', $otherLocation->id)->sole(), $otherHolder, $this->manager);
        $other->lock();
        $data['is_admin'] = false;
        $data['event_access'] = [['event_id' => $this->event->id, 'role_id' => $member->role_id], ['event_id' => $other->id, 'role_id' => null]];
        $this->put(route('settings.team.update', $this->receiver->person), $data)->assertSessionHasErrors('is_admin');
        try {
            app(GlobalTeamService::class)->update($this->receiver->person, $data, $this->manager);
            $this->fail('Direct service cannot remove admin with stock.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('is_admin', $error->errors());
        }
        $this->assertTrue($this->receiver->fresh()->is_admin);
        $other->unlock();
        app(StockMovementService::class)->move($otherItem, MovementType::TakeBack, 1, $otherHolder,
            StockHolder::where('location_id', $otherLocation->id)->sole(), $this->manager);
        app(StockMovementService::class)->move($this->item, MovementType::TakeBack, 2,
            StockHolder::where('person_id', $this->receiver->person_id)->where('event_id', $this->event->id)->sole(),
            StockHolder::where('location_id', $this->location->id)->sole(), $this->manager);
        $this->assertFalse(app(HeldStockGuard::class)->hasStock($this->receiver->person_id));
        $this->put(route('settings.team.update', $this->receiver->person), $data)->assertSessionHasNoErrors();
        $this->assertFalse($this->receiver->fresh()->is_admin);
        $this->patch(route('team.members.status.update', $member), ['status' => 'declined'])->assertSessionHasNoErrors();
    }

    public function test_stock_removal_errors_are_not_disclosed_to_unauthorized_team_editors(): void
    {
        $this->pull($this->receiver, 2);
        $viewer = $this->member(['team.notes.add']);
        $member = $this->teamMember($this->receiver);
        $this->actingAs($viewer)->put(route('team.members.update', $member), ['role_id' => null])
            ->assertSessionHasErrors(['role_id' => __('permissions.forbidden')]);
        try {
            app(TeamEngagementService::class)->update($member, $viewer, ['role_id' => null]);
            $this->fail('Role removal must be authorized before examining held stock.');
        } catch (ValidationException $error) {
            $this->assertSame([__('permissions.forbidden')], $error->errors()['role_id']);
        }
    }

    public function test_non_hired_manager_reads_holdings_but_cannot_pull_or_return(): void
    {
        $this->teamMember($this->manager)->update(['status' => 'applied']);
        $this->get(route('check-in.inventory.index'))->assertInertia(fn (Assert $p) => $p
            ->where('canManage', true)->where('canPull', false)->where('canMove', false));
        $this->post(route('check-in.inventory.pulls.store', $this->event), $this->pullData())->assertForbidden();
        $this->assertDatabaseCount('stock_movements', 1);
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->createTeamEngagement(['event_id' => $this->event->id, 'person_id' => $user->person_id,
            'role_id' => $role->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $user->setCurrentEvent($this->event);

        return $user;
    }

    private function teamMember(User $user): TeamEngagement
    {
        return TeamEngagement::where('event_id', $this->event->id)->where('person_id', $user->person_id)->sole();
    }

    private function newEvent(string $name): Event
    {
        return Event::create(['name' => $name, 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
    }

    private function pullData(?User $receiver = null, mixed $quantity = 1): array
    {
        return ['location_id' => $this->location->id, 'entitlement_item_id' => $this->item->id,
            'person_id' => ($receiver ?? $this->receiver)->person_id, 'quantity' => $quantity];
    }

    private function pull(User $receiver, int $quantity): void
    {
        app(InventoryMovementService::class)->pull($this->event, $this->manager, $this->pullData($receiver, $quantity));
    }

    private function balance(User $user): int
    {
        return app(StockMovementService::class)->balance($this->item,
            StockHolder::where('event_id', $this->event->id)->where('person_id', $user->person_id)->sole());
    }
}
