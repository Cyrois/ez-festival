<?php

namespace Tests\Feature;

use App\Enums\StockMovement as MovementType;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\Location;
use App\Models\Shift;
use App\Models\StockHolder;
use App\Models\StockMovement;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\InventoryHoldingsQuery;
use App\Services\InventoryMovementService;
use App\Services\StockMovementService;
use App\Support\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InventoryHoldingsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $manager;

    private User $supervisor;

    private User $person;

    private Location $location;

    private Shift $shift;

    private EntitlementItem $item;

    private EntitlementItem $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = Event::create(['name' => 'Festival', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
        $this->manager = $this->member(['inventory.manage']);
        $this->supervisor = $this->member(['team.view']);
        $this->person = $this->member(['checkin.edit']);
        $this->location = $this->event->locations()->create(['name' => 'Gate']);
        $this->item = $this->event->entitlementItems()->create(['name' => 'Wristband', 'low_stock_level' => 5]);
        $this->second = $this->event->entitlementItems()->create(['name' => 'Voucher', 'low_stock_level' => 0]);
        $this->shift = $this->shift([$this->supervisor, $this->person]);
        $this->shift->assignments()->where('team_engagement_id', $this->record($this->supervisor)->id)->update(['is_supervisor' => true]);
        $this->seedStock($this->item, $this->location, 20, $this->manager);
        $this->seedStock($this->second, $this->location, 20, $this->manager);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->actingAs($this->manager);
    }

    public function test_supervisor_can_reach_only_scoped_holdings_without_other_inventory_permissions(): void
    {
        $other = $this->shift([$this->manager]);
        $this->actingAs($this->supervisor)->get(route('check-in.inventory.index'))->assertInertia(fn (Assert $p) => $p
            ->component('CheckIn/Inventory')->where('canManage', false)->where('canViewInventory', true)->where('canReceive', false)
            ->has('items', 2)->missing('holdings')->where('permissions', fn ($permissions) => ! $permissions['inventory.manage'] && ! $permissions['checkin.edit']));
        $this->getJson(route('check-in.inventory.scopes', ['type' => 'shift']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->shift->id);
        $this->getJson(route('check-in.inventory.scopes', ['type' => 'group']))->assertForbidden();
        $this->getJson(route('check-in.inventory.holdings', ['shift_id' => $other->id]))->assertForbidden();
        $this->getJson(route('check-in.inventory.holdings', ['group_id' => $this->record($this->person)->group_id]))->assertForbidden();
        foreach (['locations', 'availability', 'adjustment-people', 'adjustment-stock'] as $name) {
            $this->getJson(route('check-in.inventory.'.$name))->assertForbidden();
        }
        foreach (['index', 'data', 'options', 'export'] as $name) {
            $this->getJson(route('check-in.history.'.$name))->assertForbidden();
        }
        $this->get(route('check-in.index'))->assertForbidden();
        $this->getJson(route('check-in.inventory.holdings.stock', ['shift_id' => $this->shift->id, 'person_id' => $this->manager->person_id]))->assertForbidden();
        $this->assertDatabaseCount('stock_holders', 1);
        $this->shift->assignments()->update(['is_supervisor' => false]);
        $this->get(route('check-in.inventory.index'))->assertForbidden();
    }

    public function test_roster_includes_zero_and_inactive_people_is_paged_and_never_creates_holders(): void
    {
        $inactive = $this->member(['checkin.view']);
        $this->assign($this->shift, $inactive);
        for ($i = 0; $i < 26; $i++) {
            $this->assign($this->shift, $this->member(['checkin.edit']));
        }
        $url = route('check-in.inventory.holdings', ['shift_id' => $this->shift->id]);
        $first = $this->getJson($url)->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('recordsTotal', 29);
        foreach ($first->json('data') as $row) {
            $this->assertSame(['id', 'name', 'inactive', 'is_supervisor', 'items'], array_keys($row));
        }
        $this->getJson($url.'&start=25')->assertOk()->assertJsonCount(4, 'data');
        $this->assertDatabaseCount('stock_holders', 1);
        $this->getJson(route('check-in.inventory.holdings'))->assertUnprocessable();
        $this->getJson(route('check-in.inventory.holdings', ['shift_id' => $this->shift->id, 'group_id' => $this->record($this->person)->group_id]))->assertUnprocessable();
    }

    public function test_manager_give_is_atomic_and_rows_reconcile_across_shift_and_team(): void
    {
        $data = $this->giveData();
        $data['items'][1]['quantity'] = 21;
        $this->postJson(route('check-in.inventory.gives.store', $this->event), $data)->assertUnprocessable()
            ->assertJsonValidationErrors('items.1.quantity')->assertJson(fn ($json) => $json->where('errors', fn ($errors) => $errors['items.1.quantity'][0] === __('inventory.errors.insufficient_stock', ['count' => 20]))->etc());
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseCount('stock_holders', 1);
        $this->post(route('check-in.inventory.gives.store', $this->event), $this->giveData())->assertSessionHasNoErrors();
        $this->assertSame(3, $this->balance($this->person, $this->item));
        $this->assertDatabaseCount('stock_movements', 4);
        $rows = [];
        foreach ([['shift_id' => $this->shift->id], ['group_id' => $this->record($this->person)->group_id]] as $scope) {
            $rows[] = collect($this->getJson(route('check-in.inventory.holdings', $scope))->assertOk()->json('data'))->firstWhere('id', $this->person->person_id)['items'];
        }
        $this->assertSame($rows[0], $rows[1]);
        $this->assertSame([$this->manager->id], StockMovement::where('type', MovementType::Pull)->pluck('actor_user_id')->unique()->all());
    }

    public function test_supervisor_acts_before_during_and_long_after_shift_without_pass_permission(): void
    {
        $this->seedHeldStock($this->item, $this->location, 6, $this->supervisor);
        foreach (['2027-05-01 12:00', '2027-06-01 11:00', '2027-08-01 12:00'] as $time) {
            $this->travelTo(CarbonImmutable::parse($time, $this->event->timezone));
            $this->actingAs($this->supervisor)->getJson(route('check-in.inventory.holdings', ['shift_id' => $this->shift->id]))->assertOk();
            $this->post(route('check-in.inventory.gives.store', $this->event), [
                'shift_id' => $this->shift->id, 'person_id' => $this->person->person_id,
                'items' => [['entitlement_item_id' => $this->item->id, 'quantity' => 1]],
            ])->assertSessionHasNoErrors();
            $this->post(route('check-in.inventory.take-backs.store', $this->event), $this->takeData(1))->assertSessionHasNoErrors();
        }
        $this->travelBack();
        $this->assertSame(0, $this->balance($this->person, $this->item));
        $this->assertSame(3, StockMovement::where('type', MovementType::TakeBack)->count());
    }

    public function test_supervisor_cannot_use_location_stock_or_outside_people_and_receiver_still_must_qualify(): void
    {
        $this->seedHeldStock($this->item, $this->location, 5, $this->person);
        $this->actingAs($this->supervisor)->postJson(route('check-in.inventory.gives.store', $this->event), $this->giveData())->assertForbidden();
        $data = $this->takeData(1);
        $data['destination'] = 'person';
        unset($data['location_id']);
        $data['receiver_id'] = $this->manager->person_id;
        $this->postJson(route('check-in.inventory.take-backs.store', $this->event), $data)->assertForbidden();
        $this->postJson(route('check-in.inventory.take-backs.store', $this->event), [...$data, 'destination' => 'me', 'receiver_id' => null])->assertUnprocessable();
        $ids = $this->getJson(route('check-in.inventory.holdings.receivers', ['shift_id' => $this->shift->id, 'person_id' => $this->person->person_id]))->assertOk()->json('data.*.id');
        $this->assertSame([], $ids);
        $this->postJson(route('check-in.inventory.adjustments.store', $this->event), [])->assertForbidden();
        $this->assertSame(5, $this->balance($this->person, $this->item));
    }

    public function test_take_back_prefill_is_complete_partial_zero_and_all_zero_rules_are_atomic(): void
    {
        $this->post(route('check-in.inventory.gives.store', $this->event), $this->giveData())->assertSessionHasNoErrors();
        $stock = $this->getJson(route('check-in.inventory.holdings.stock', ['shift_id' => $this->shift->id, 'person_id' => $this->person->person_id]))->assertOk()->json('data');
        $this->assertCount(2, $stock);
        $this->postJson(route('check-in.inventory.take-backs.store', $this->event), $this->takeData(0))->assertUnprocessable()->assertJsonValidationErrors('items');
        $data = $this->takeData(2);
        $data['items'][] = ['entitlement_item_id' => $this->second->id, 'quantity' => 99];
        $this->postJson(route('check-in.inventory.take-backs.store', $this->event), $data)->assertUnprocessable();
        $this->assertSame(3, $this->balance($this->person, $this->item));
        $data['items'][1]['quantity'] = 0;
        $this->post(route('check-in.inventory.take-backs.store', $this->event), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, $this->balance($this->person, $this->item));
        $this->assertSame(4, $this->balance($this->person, $this->second));
        $this->assertSame(1, StockMovement::where('type', MovementType::TakeBack)->count());
    }

    public function test_adjust_recovers_inactive_people_and_classifies_both_directions_and_notes(): void
    {
        $data = ['direction' => 'from_location', 'person_id' => $this->person->person_id, 'location_id' => $this->location->id,
            'items' => [['entitlement_item_id' => $this->item->id, 'quantity' => 3]]];
        $this->post(route('check-in.inventory.adjustments.store', $this->event), $data)->assertSessionHasNoErrors();
        $this->assertSame(MovementType::Pull, StockMovement::latest('id')->first()->type);
        $this->record($this->person)->role->update(['permissions' => ['checkin.view']]);
        $ids = $this->getJson(route('check-in.inventory.adjustment-people', ['direction' => 'to_location']))->assertOk()->json('data.*.id');
        $this->assertContains($this->person->person_id, $ids);
        $ids = $this->getJson(route('check-in.inventory.adjustment-people', ['direction' => 'from_location']))->assertOk()->json('data.*.id');
        $this->assertNotContains($this->person->person_id, $ids);
        $data['direction'] = 'to_location';
        $data['note'] = 'Shift ended';
        $this->post(route('check-in.inventory.adjustments.store', $this->event), $data)->assertSessionHasNoErrors();
        $movement = StockMovement::latest('id')->first();
        $this->assertSame(MovementType::TakeBack, $movement->type);
        $this->assertSame('Shift ended', $movement->note);
        $this->seedHeldStock($this->item, $this->location, 2, $this->manager);
        $data['person_id'] = $this->manager->person_id;
        $data['items'][0]['quantity'] = 2;
        $this->post(route('check-in.inventory.adjustments.store', $this->event), $data)->assertSessionHasNoErrors();
        $this->assertSame(MovementType::Return, StockMovement::latest('id')->first()->type);
        $this->getJson(route('check-in.history.data'))->assertOk()->assertJsonPath('data.0.type', 'return');
    }

    public function test_writes_validate_foreign_ids_duplicates_bad_quantities_and_locked_events(): void
    {
        $foreign = Event::create(['name' => 'Other', 'starts_on' => '2027-01-01', 'ends_on' => '2027-01-02', 'timezone' => 'UTC']);
        $otherItem = $foreign->entitlementItems()->create(['name' => 'Other']);
        foreach ([-1, 0, 1.5, 'text', 2147483648] as $quantity) {
            $data = $this->giveData();
            $data['items'][0]['quantity'] = $quantity;
            $this->postJson(route('check-in.inventory.gives.store', $this->event), $data)->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        }
        $data = $this->giveData();
        $data['items'][0]['entitlement_item_id'] = $otherItem->id;
        $this->postJson(route('check-in.inventory.gives.store', $this->event), $data)->assertUnprocessable();
        $data = $this->giveData();
        $data['items'][1]['entitlement_item_id'] = $this->item->id;
        $this->postJson(route('check-in.inventory.gives.store', $this->event), $data)->assertUnprocessable();
        $this->event->lock();
        foreach (['gives', 'take-backs', 'adjustments'] as $name) {
            $this->postJson(route('check-in.inventory.'.$name.'.store', $this->event), $this->giveData())->assertForbidden();
        }
        $this->getJson(route('check-in.inventory.holdings', ['shift_id' => $this->shift->id]))->assertOk();
        try {
            app(InventoryMovementService::class)->give($this->event, $this->manager, $this->giveData());
            $this->fail('Stale event must still refuse writes.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_remote_pickers_accept_the_empty_search_sent_by_the_ui(): void
    {
        $this->getJson(route('check-in.inventory.adjustment-people', ['direction' => 'from_location', 'search' => '', 'page' => 1]))->assertOk();
        $this->getJson(route('check-in.inventory.scopes', ['type' => 'shift', 'search' => '', 'page' => 1]))->assertOk();
        $this->actingAs($this->supervisor)->getJson(route('check-in.inventory.holdings.receivers', [
            'person_id' => $this->person->person_id, 'shift_id' => $this->shift->id, 'search' => '', 'page' => 1,
        ]))->assertOk();
    }

    public function test_read_query_count_is_constant_as_roster_and_items_grow(): void
    {
        $query = app(InventoryHoldingsQuery::class);
        $read = function () use ($query): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $rows = $query->people($this->event, ['shift_id' => $this->shift->id])->limit(25)->get();
            $query->rows($this->event, $rows);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };
        $small = $read();
        for ($i = 0; $i < 18; $i++) {
            $this->assign($this->shift, $this->member(['checkin.edit']));
            $this->event->entitlementItems()->create(['name' => 'Extra '.$i]);
        }
        $this->assertSame($small, $read());
    }

    public function test_shift_picker_orders_in_progress_upcoming_and_past_and_never_leaks_foreign_scopes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-06-01 11:00', $this->event->timezone));
        $past = $this->shift([$this->supervisor]);
        $past->update(['name' => null, 'starts_at' => '2027-05-01 10:00', 'ends_at' => '2027-05-01 12:00']);
        $future = $this->shift([$this->supervisor]);
        $future->update(['starts_at' => '2027-07-01 10:00', 'ends_at' => '2027-07-01 12:00']);
        foreach ([$past, $future] as $shift) {
            $shift->assignments()->update(['is_supervisor' => true]);
        }
        $this->actingAs($this->supervisor);
        $this->assertSame([$this->shift->id, $future->id, $past->id], $this->getJson(route('check-in.inventory.scopes', ['type' => 'shift']))->assertOk()->json('data.*.id'));
        $foreign = Event::create(['name' => 'Foreign', 'starts_on' => '2027-01-01', 'ends_on' => '2027-01-02', 'timezone' => 'UTC']);
        $group = $foreign->groups()->create(['name' => 'Foreign group']);
        $this->getJson(route('check-in.inventory.holdings', ['group_id' => $group->id]))->assertUnprocessable();
        $this->travelBack();
    }

    public function test_fresh_supervision_and_recipient_assignment_are_rechecked_on_service_writes(): void
    {
        $data = $this->takeData(1);
        $this->seedHeldStock($this->item, $this->location, 3, $this->person);
        $this->shift->assignments()->where('is_supervisor', true)->update(['is_supervisor' => false]);
        try {
            app(InventoryMovementService::class)->takeBackItems($this->event, $this->supervisor, $data);
            $this->fail('Revoked supervision must refuse a stale dialog.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->shift->assignments()->where('team_engagement_id', $this->record($this->supervisor)->id)->update(['is_supervisor' => true]);
        $this->shift->assignments()->where('team_engagement_id', $this->record($this->person)->id)->delete();
        $this->actingAs($this->supervisor)->postJson(route('check-in.inventory.take-backs.store', $this->event), $data)->assertForbidden();
        $this->assertSame(3, $this->balance($this->person, $this->item));
    }

    public function test_supervisor_can_send_a_take_back_only_to_an_eligible_person_on_selected_shift(): void
    {
        $receiver = $this->member(['checkin.edit']);
        $this->assign($this->shift, $receiver);
        $this->seedHeldStock($this->item, $this->location, 3, $this->person);
        $data = $this->takeData(2);
        unset($data['location_id']);
        $data['destination'] = 'person';
        $data['receiver_id'] = $receiver->person_id;
        $this->actingAs($this->supervisor)->post(route('check-in.inventory.take-backs.store', $this->event), $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $this->balance($receiver, $this->item));
        $this->record($receiver)->role->update(['permissions' => ['checkin.view']]);
        $data['items'][0]['quantity'] = 1;
        $this->postJson(route('check-in.inventory.take-backs.store', $this->event), $data)->assertUnprocessable()->assertJsonValidationErrors('receiver_id');
        $this->assertSame(1, $this->balance($this->person, $this->item));
    }

    public function test_adjust_rejects_unsupported_directions_notes_bad_values_and_rolls_back_partial_sets(): void
    {
        $data = ['direction' => 'from_location', 'person_id' => $this->person->person_id, 'location_id' => $this->location->id,
            'items' => [['entitlement_item_id' => $this->item->id, 'quantity' => 2], ['entitlement_item_id' => $this->second->id, 'quantity' => 99]]];
        $this->postJson(route('check-in.inventory.adjustments.store', $this->event), $data)->assertUnprocessable()->assertJsonValidationErrors('items.1.quantity');
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseCount('stock_holders', 1);
        $data['items'] = [$data['items'][0]];
        $this->postJson(route('check-in.inventory.adjustments.store', $this->event), [...$data, 'direction' => 'location_to_location'])->assertUnprocessable()->assertJsonValidationErrors('direction');
        $this->postJson(route('check-in.inventory.adjustments.store', $this->event), [...$data, 'note' => 'Not allowed'])->assertUnprocessable()->assertJsonValidationErrors('note');
        foreach ([0, -1, 1.5] as $quantity) {
            $data['items'][0]['quantity'] = $quantity;
            $this->postJson(route('check-in.inventory.adjustments.store', $this->event), $data)->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        }
        $this->assertDatabaseCount('stock_movements', 2);
    }

    private function member(array $permissions): User
    {
        $user = $this->grantRoleAccess(User::factory()->create(), $permissions);
        $user->setCurrentEvent($this->event);

        return $user;
    }

    private function record(User $user): TeamEngagement
    {
        return TeamEngagement::where('event_id', $this->event->id)->where('person_id', $user->person_id)->sole();
    }

    private function assign(Shift $shift, User $person): void
    {
        $member = $this->record($person);
        $shift->assignments()->create(['team_engagement_id' => $member->id, 'role_id' => $member->role_id, 'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at]);
    }

    private function shift(array $people): Shift
    {
        $shift = $this->event->shifts()->create(['name' => 'Gate shift', 'location_id' => $this->location->id, 'starts_at' => '2027-06-01 10:00', 'ends_at' => '2027-06-01 12:00']);
        foreach ($people as $person) {
            $this->assign($shift, $person);
        }

        return $shift;
    }

    private function giveData(): array
    {
        return ['shift_id' => $this->shift->id, 'person_id' => $this->person->person_id, 'items' => [
            ['entitlement_item_id' => $this->item->id, 'quantity' => 3, 'location_id' => $this->location->id],
            ['entitlement_item_id' => $this->second->id, 'quantity' => 4, 'location_id' => $this->location->id],
        ]];
    }

    private function takeData(int $quantity): array
    {
        return ['shift_id' => $this->shift->id, 'person_id' => $this->person->person_id, 'destination' => 'location', 'location_id' => $this->location->id,
            'items' => [['entitlement_item_id' => $this->item->id, 'quantity' => $quantity]]];
    }

    private function balance(User $user, EntitlementItem $item): int
    {
        return app(StockMovementService::class)->balance($item, StockHolder::where('event_id', $this->event->id)->where('person_id', $user->person_id)->sole());
    }
}
