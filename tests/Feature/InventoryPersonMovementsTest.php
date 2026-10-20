<?php

namespace Tests\Feature;

use App\Enums\StockMovement as MovementType;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\Group;
use App\Models\Location;
use App\Models\Role;
use App\Models\Shift;
use App\Models\StockHolder;
use App\Models\StockMovement;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\InventoryTargetsQuery;
use App\Services\EventService;
use App\Services\HeldStockGuard;
use App\Services\InventoryMovementService;
use App\Services\StockMovementService;
use App\Services\TeamEngagementService;
use App\Support\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryPersonMovementsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Event $event;

    private User $manager;

    private User $source;

    private Location $location;

    private EntitlementItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = Event::create(['name' => 'Festival', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->manager = $this->member(['inventory.manage']);
        $this->source = $this->member(['checkin.edit']);
        $this->location = $this->event->locations()->create(['name' => 'Gate']);
        $this->item = $this->event->entitlementItems()->create(['name' => 'Wristband']);
        $this->seedStock($this->item, $this->location, 20, $this->manager);
        app(InventoryMovementService::class)->pull($this->event, $this->manager, [
            'location_id' => $this->location->id, 'person_id' => $this->source->person_id,
            'entitlement_item_id' => $this->item->id, 'quantity' => 10,
        ]);
        $this->actingAs($this->source);
    }

    public function test_return_to_managers_and_admins_needs_no_pass_permission_and_records_partial_and_all(): void
    {
        $admin = $this->grantAdminAccess(User::factory()->create());
        $ids = $this->getJson(route('check-in.inventory.move-receivers'))->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$this->manager->person_id, $admin->person_id], $ids);
        foreach ([[$this->manager, 3], [$admin, 7]] as [$target, $quantity]) {
            $this->put(route('check-in.inventory.returns.update', $this->event), $this->data($target, $quantity))
                ->assertRedirect()->assertSessionHasNoErrors();
            $move = StockMovement::latest('id')->first();
            $this->assertSame(MovementType::Return, $move->type);
            $this->assertSame($this->source->id, $move->actor_user_id);
            $this->assertSame($this->source->person_id, $move->fromHolder->person_id);
            $this->assertSame($target->person_id, $move->toHolder->person_id);
        }
        $this->assertSame(0, $this->balance($this->source));
        $this->assertFalse(app(HeldStockGuard::class)->hasStock($this->source->person_id, $this->event->id));
    }

    public function test_manager_returns_refuse_self_non_managers_and_ambiguous_destinations(): void
    {
        $other = $this->member(['checkin.edit']);
        foreach ([$this->source, $other] as $target) {
            $this->put(route('check-in.inventory.returns.update', $this->event), $this->data($target))->assertSessionHasErrors('person_id');
        }
        $this->put(route('check-in.inventory.returns.update', $this->event), [
            ...$this->data($this->manager), 'location_id' => $this->location->id,
        ])->assertSessionHasErrors(['location_id', 'person_id']);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_pass_permissions_union_shift_and_group_without_exposing_other_holdings(): void
    {
        $this->role($this->source)->update(['permissions' => ['inventory.pass_shift', 'inventory.pass_group']]);
        $group = Group::create(['event_id' => $this->event->id, 'name' => 'Artist desk']);
        $this->memberRecord($this->source)->update(['group_id' => $group->id]);
        $shiftOnly = $this->member(['checkin.edit']);
        $groupOnly = $this->member(['inventory.manage'], $group);
        $both = $this->member(['checkin.edit'], $group);
        $ineligible = $this->member(['checkin.view'], $group);
        $unrelated = $this->member(['checkin.edit']);
        $this->shift([$this->source, $shiftOnly, $both, $ineligible]);
        $this->travelTo(CarbonImmutable::parse('2027-06-01 11:00', $this->event->timezone));
        $rows = collect($this->getJson(route('check-in.inventory.move-receivers'))->assertOk()->assertJsonCount(4, 'data')->json('data'));
        $this->assertSame(true, $rows->firstWhere('id', $this->manager->person_id)['is_manager']);
        $this->assertSame(true, $rows->firstWhere('id', $groupOnly->person_id)['is_manager']);
        $this->assertCount(1, $rows->where('id', $groupOnly->person_id));
        $this->assertSame(true, $rows->firstWhere('id', $shiftOnly->person_id)['on_shift']);
        $this->assertSame(false, $rows->firstWhere('id', $shiftOnly->person_id)['in_group']);
        $this->assertSame(false, $rows->firstWhere('id', $groupOnly->person_id)['on_shift']);
        $this->assertSame(false, $rows->firstWhere('id', $groupOnly->person_id)['in_group']);
        $this->assertEqualsCanonicalizing([$shiftOnly->person_id, $groupOnly->person_id, $both->person_id], $this->passIds());
        $this->post(route('check-in.inventory.transfers.store', $this->event), $this->data($groupOnly, 4))->assertSessionHasNoErrors();
        $move = StockMovement::latest('id')->first();
        $this->assertSame(MovementType::Return, $move->type);
        $this->assertSame($this->source->id, $move->actor_user_id);
        $this->assertSame(6, $this->balance($this->source));
        foreach ([$this->source, $ineligible, $unrelated, $this->manager] as $target) {
            $this->post(route('check-in.inventory.transfers.store', $this->event), $this->data($target))->assertSessionHasErrors('person_id');
        }
        $this->getJson(route('check-in.inventory.holdings'))->assertForbidden();
        $this->getJson(route('check-in.inventory.holders.receivers', $this->holder()))->assertForbidden();
    }

    public function test_group_pass_works_off_shift_and_different_groups_do_not_match(): void
    {
        $this->role($this->source)->update(['permissions' => ['inventory.pass_group']]);
        $group = Group::create(['event_id' => $this->event->id, 'name' => 'Crew']);
        $target = $this->member(['checkin.edit'], $group);
        $this->assertSame([], $this->passIds());
        $this->memberRecord($this->source)->update(['group_id' => $group->id]);
        $this->assertSame([$target->person_id], $this->passIds());
        $this->get(route('check-in.my-stock'))->assertInertia(fn (Assert $p) => $p->where('canMove', true));
        $this->post(route('check-in.inventory.transfers.store', $this->event), $this->data($target))->assertSessionHasNoErrors();
        $this->role($this->source)->update(['permissions' => ['inventory.pass_shift']]);
        $this->assertSame([], $this->passIds());
    }

    public function test_both_people_use_inclusive_parent_shift_grace_in_event_timezone_even_with_different_assignment_hours(): void
    {
        $this->role($this->source)->update(['permissions' => ['inventory.pass_shift']]);
        $target = $this->member(['checkin.edit']);
        $shift = $this->shift([$this->source, $target]);
        $shift->assignments()->where('team_engagement_id', $this->memberRecord($this->source)->id)
            ->update(['starts_at' => '2027-06-01 10:00', 'ends_at' => '2027-06-01 10:30']);
        $shift->assignments()->where('team_engagement_id', $this->memberRecord($target)->id)
            ->update(['starts_at' => '2027-06-01 11:30', 'ends_at' => '2027-06-01 12:00']);
        foreach (['09:29:59' => false, '09:30:00' => true, '11:00:00' => true, '12:30:00' => true, '12:30:01' => false] as $time => $allowed) {
            $this->travelTo(CarbonImmutable::parse('2027-06-01 '.$time, $this->event->timezone));
            $this->assertSame($allowed ? [$target->person_id] : [], $this->passIds(), $time);
            $ids = $this->getJson(route('check-in.inventory.move-receivers'))->assertOk()->json('data.*.id');
            $this->assertSame($allowed, in_array($target->person_id, $ids, true));
            $this->assertContains($this->manager->person_id, $ids);
        }
    }

    public function test_overnight_and_multiple_shift_windows_are_unioned_without_accepting_different_shifts(): void
    {
        $this->role($this->source)->update(['permissions' => ['inventory.pass_shift']]);
        $first = $this->member(['checkin.edit']);
        $second = $this->member(['inventory.manage']);
        $unrelated = $this->member(['checkin.edit']);
        $this->shift([$this->source, $first], '2027-06-01 22:00', '2027-06-02 02:00');
        $this->shift([$this->source, $second], '2027-06-02 02:15', '2027-06-02 05:00');
        $this->shift([$unrelated], '2027-06-01 22:00', '2027-06-02 05:00');
        $this->travelTo(CarbonImmutable::parse('2027-06-02 02:30', $this->event->timezone));
        $this->assertEqualsCanonicalizing([$first->person_id, $second->person_id], $this->passIds());
        $this->travel(1)->seconds();
        $this->assertSame([$second->person_id], $this->passIds());
    }

    public function test_no_pass_permission_keeps_move_available_with_only_managers_and_forbids_direct_transfer(): void
    {
        $this->get(route('check-in.my-stock'))->assertInertia(fn (Assert $p) => $p->where('canMove', true)->missing('canPass'));
        $this->getJson(route('check-in.inventory.move-receivers'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->manager->person_id)->assertJsonPath('data.0.is_manager', true);
        $this->post(route('check-in.inventory.transfers.store', $this->event), $this->data($this->manager))->assertForbidden();
        $this->post(route('check-in.inventory.take-backs.store', [$this->event, $this->holder()]), $this->takeBackData())->assertForbidden();
        $this->getJson(route('check-in.inventory.receivers'))->assertForbidden();
    }

    public function test_managers_and_admins_pass_to_any_eligible_person_without_shift_or_group(): void
    {
        $standaloneAdmin = $this->grantAdminAccess(User::factory()->create());
        foreach ([$this->manager, $standaloneAdmin] as $actor) {
            $this->actingAs($actor);
            $ids = $this->getJson(route('check-in.inventory.move-receivers'))->assertOk()->json('data.*.id');
            $this->assertContains($this->source->person_id, $ids);
            $this->assertNotContains($actor->person_id, $ids);
        }
    }

    public function test_combined_move_picker_and_writes_classify_locations_managers_and_people(): void
    {
        $this->role($this->source)->update(['permissions' => ['inventory.pass_group']]);
        $group = Group::create(['event_id' => $this->event->id, 'name' => 'Crew']);
        $this->memberRecord($this->source)->update(['group_id' => $group->id]);
        $person = $this->member(['checkin.edit'], $group);
        $this->memberRecord($this->manager)->update(['group_id' => $group->id]);
        $rows = collect($this->getJson(route('check-in.inventory.move-receivers'))->assertOk()->json('data'));
        $this->assertEqualsCanonicalizing([$this->manager->person_id, $person->person_id], $rows->pluck('id')->all());
        $this->assertSame(true, $rows->firstWhere('id', $this->manager->person_id)['is_manager']);
        $this->assertSame(false, $rows->firstWhere('id', $person->person_id)['is_manager']);
        $this->assertSame(true, $rows->firstWhere('id', $person->person_id)['in_group']);
        $this->post(route('check-in.inventory.transfers.store', $this->event), $this->data($person))->assertSessionHasNoErrors();
        $this->assertSame(MovementType::Transfer, StockMovement::latest('id')->first()->type);
        // A receiver who becomes a manager after the picker loaded must also record Return.
        $this->role($person)->update(['permissions' => ['inventory.manage']]);
        $this->post(route('check-in.inventory.transfers.store', $this->event), $this->data($person))->assertSessionHasNoErrors();
        $this->assertSame(MovementType::Return, StockMovement::latest('id')->first()->type);
        $this->put(route('check-in.inventory.returns.update', $this->event), [
            'location_id' => $this->location->id, 'entitlement_item_id' => $this->item->id, 'quantity' => 2,
        ])->assertSessionHasNoErrors();
        $this->assertSame(MovementType::Return, StockMovement::latest('id')->first()->type);
        $this->assertSame(6, $this->balance($this->source));
    }

    public function test_take_back_to_me_location_and_person_records_actor_note_and_holder_history(): void
    {
        $target = $this->member(['checkin.edit']);
        $holder = $this->holder();
        $this->actingAs($this->manager);
        $ids = $this->getJson(route('check-in.inventory.holders.receivers', $holder))->assertOk()->json('data.*.id');
        $this->assertNotContains($this->source->person_id, $ids);
        foreach (['me', 'location', 'person'] as $destination) {
            $data = $this->takeBackData($destination, $target);
            $this->post(route('check-in.inventory.take-backs.store', [$this->event, $holder]), $data)->assertSessionHasNoErrors();
            $move = StockMovement::latest('id')->first();
            $this->assertSame(MovementType::TakeBack, $move->type);
            $this->assertSame($this->manager->id, $move->actor_user_id);
            $this->assertSame($holder->id, $move->from_holder_id);
            $this->assertSame('End of shift', $move->note);
            if ($destination === 'location') {
                $this->assertSame($this->location->id, $move->toHolder->location_id);
            } else {
                $this->assertSame(($destination === 'me' ? $this->manager : $target)->person_id, $move->toHolder->person_id);
            }
        }
        $this->actingAs($this->source)->getJson(route('check-in.inventory.movements'))->assertOk()
            ->assertJsonPath('data.0.type', 'take_back')->assertJsonPath('data.0.taken_from_me', true)
            ->assertJsonPath('data.0.recorded_by', $this->manager->name)->assertJsonPath('data.0.receiver', $target->name);
        $this->assertSame(7, $this->balance($this->source));
    }

    public function test_take_back_clears_inactive_person_stock_and_lifts_removal_guard_without_holder_consent(): void
    {
        $this->role($this->source)->update(['permissions' => ['checkin.view']]);
        $this->put(route('check-in.inventory.returns.update', $this->event), $this->data($this->manager))->assertForbidden();
        $this->actingAs($this->manager)->post(route('check-in.inventory.take-backs.store', [$this->event, $this->holder()]), [
            ...$this->takeBackData(), 'quantity' => 10,
        ])->assertSessionHasNoErrors();
        $this->assertFalse(app(HeldStockGuard::class)->hasStock($this->source->person_id, $this->event->id));
        app(HeldStockGuard::class)->ensureClear($this->source->person_id, $this->event->id, 'role_id');
        app(TeamEngagementService::class)->updateStatus($this->memberRecord($this->source), 'declined');
        $this->assertSame('declined', $this->memberRecord($this->source)->status);
        $this->assertSame(0, $this->balance($this->source));
    }

    public function test_invalid_quantities_foreign_event_ids_and_self_take_back_leave_ledger_unchanged(): void
    {
        $this->actingAs($this->manager);
        $holder = $this->holder();
        foreach ([0, -1, 1.5, 11, 2147483648] as $quantity) {
            $this->post(route('check-in.inventory.take-backs.store', [$this->event, $holder]), [...$this->takeBackData(), 'quantity' => $quantity])->assertSessionHasErrors('quantity');
        }
        $this->post(route('check-in.inventory.take-backs.store', [$this->event, $holder]), $this->takeBackData('person', $this->source))->assertSessionHasErrors('person_id');
        $this->post(route('check-in.inventory.take-backs.store', [$this->event, $holder]), [...$this->takeBackData(), 'note' => str_repeat('x', 256)])->assertSessionHasErrors('note');
        $foreign = Event::create(['name' => 'Other', 'starts_on' => '2027-07-01', 'ends_on' => '2027-07-02', 'timezone' => 'UTC']);
        $foreignHolder = app(StockMovementService::class)->forPerson($foreign, $this->source->person);
        $foreignLocation = $foreign->locations()->create(['name' => 'Other Gate']);
        $foreignItem = $foreign->entitlementItems()->create(['name' => 'Other item']);
        $this->post(route('check-in.inventory.take-backs.store', [$this->event, $foreignHolder]), $this->takeBackData())->assertNotFound();
        $this->post(route('check-in.inventory.take-backs.store', [$foreign, $foreignHolder]), [...$this->takeBackData(), 'entitlement_item_id' => $foreignItem->id])->assertNotFound();
        foreach (['location_id' => $foreignLocation->id, 'entitlement_item_id' => $foreignItem->id] as $key => $id) {
            $this->post(route('check-in.inventory.take-backs.store', [$this->event, $holder]), [...$this->takeBackData('location'), $key => $id])->assertSessionHasErrors($key);
        }
        $locationHolder = StockHolder::where('location_id', $this->location->id)->sole();
        $this->post(route('check-in.inventory.take-backs.store', [$this->event, $locationHolder]), $this->takeBackData())->assertNotFound();
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_save_rechecks_expired_shift_and_revoked_receiver_and_manager_eligibility(): void
    {
        $this->role($this->source)->update(['permissions' => ['inventory.pass_shift']]);
        $target = $this->member(['checkin.edit']);
        $this->shift([$this->source, $target]);
        $this->travelTo(CarbonImmutable::parse('2027-06-01 12:30', $this->event->timezone));
        $this->assertSame([$target->person_id], $this->passIds());
        $this->travel(1)->seconds();
        try {
            app(InventoryMovementService::class)->passStock($this->event, $this->source, $this->data($target));
            $this->fail('Expired picker must not authorize a transfer.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('person_id', $error->errors());
        }
        $this->travelBack();
        $this->role($this->manager)->update(['permissions' => ['checkin.edit']]);
        $this->put(route('check-in.inventory.returns.update', $this->event), $this->data($this->manager))->assertSessionHasErrors('person_id');
        $admin = $this->grantAdminAccess(User::factory()->create());
        $this->role($target)->update(['active' => false]);
        $this->actingAs($admin)->post(route('check-in.inventory.take-backs.store', [$this->event, $this->holder()]), $this->takeBackData('person', $target))->assertSessionHasErrors('person_id');
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_locked_event_refuses_all_three_movements_and_preserves_reads(): void
    {
        $this->event->lock();
        $this->put(route('check-in.inventory.returns.update', $this->event), $this->data($this->manager))->assertForbidden();
        $this->actingAs($this->manager)->post(route('check-in.inventory.transfers.store', $this->event), $this->data($this->source))->assertForbidden();
        $this->post(route('check-in.inventory.take-backs.store', [$this->event, $this->holder()]), $this->takeBackData())->assertForbidden();
        $this->getJson(route('check-in.inventory.holdings'))->assertOk();
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_target_search_is_literal_paged_and_scoped_and_event_delete_cleans_new_movements(): void
    {
        $this->actingAs($this->manager);
        for ($i = 0; $i < 27; $i++) {
            $person = $this->member(['checkin.edit']);
            $person->person()->update(['name' => sprintf('Receiver %02d', $i)]);
        }
        $literal = $this->member(['inventory.manage']);
        $literal->person()->update(['name' => '100%_manager!']);
        $this->getJson(route('check-in.inventory.move-receivers', ['search' => 'Receiver', 'page' => 2]))->assertJsonCount(2, 'data');
        $this->getJson(route('check-in.inventory.move-receivers', ['search' => '%_']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $literal->person_id);
        $this->post(route('check-in.inventory.take-backs.store', [$this->event, $this->holder()]), $this->takeBackData())->assertSessionHasNoErrors();
        app(EventService::class)->delete($this->event);
        $this->assertDatabaseMissing('stock_movements', ['event_id' => $this->event->id]);
        $this->assertDatabaseMissing('stock_holders', ['event_id' => $this->event->id]);
    }

    private function data(User $target, int $quantity = 1): array
    {
        return ['person_id' => $target->person_id, 'entitlement_item_id' => $this->item->id, 'quantity' => $quantity];
    }

    private function takeBackData(string $destination = 'me', ?User $target = null): array
    {
        return ['destination' => $destination, 'entitlement_item_id' => $this->item->id, 'quantity' => 1, 'note' => 'End of shift',
            ...($destination === 'location' ? ['location_id' => $this->location->id] : []),
            ...($destination === 'person' ? ['person_id' => $target->person_id] : [])];
    }

    private function member(array $permissions, ?Group $group = null): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->createTeamEngagement(['event_id' => $this->event->id, 'person_id' => $user->person_id, 'role_id' => $role->id, 'status' => 'hired', 'group_id' => $group?->id ?? Group::create(['event_id' => $this->event->id, 'name' => 'Group '.$user->id])->id]);
        $user->setCurrentEvent($this->event);

        return $user;
    }

    private function memberRecord(User $user): TeamEngagement
    {
        return TeamEngagement::where('event_id', $this->event->id)->where('person_id', $user->person_id)->sole();
    }

    private function role(User $user): Role
    {
        return $this->memberRecord($user)->role;
    }

    private function holder(): StockHolder
    {
        return StockHolder::where('event_id', $this->event->id)->where('person_id', $this->source->person_id)->sole();
    }

    private function balance(User $user): int
    {
        $holder = StockHolder::where('event_id', $this->event->id)->where('person_id', $user->person_id)->sole();

        return app(StockMovementService::class)->balance($this->item, $holder);
    }

    private function passIds(): array
    {
        return app(InventoryTargetsQuery::class)->passReceivers($this->event, auth()->user())->get()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function shift(array $people, string $start = '2027-06-01 10:00', string $end = '2027-06-01 12:00'): Shift
    {
        $shift = $this->event->shifts()->create(['location_id' => $this->location->id, 'starts_at' => $start, 'ends_at' => $end]);
        foreach ($people as $user) {
            $member = $this->memberRecord($user);
            $shift->assignments()->create(['team_engagement_id' => $member->id, 'role_id' => $member->role_id, 'starts_at' => $start, 'ends_at' => $end]);
        }

        return $shift;
    }
}
