<?php

namespace Tests\Feature;

use App\Enums\StockMovement as MovementType;
use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\Person;
use App\Models\StockHolder;
use App\Models\User;
use App\Services\EntitlementConsumeService;
use App\Services\EntitlementItemService;
use App\Services\StockMovementService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OwnStockCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_without_team_uses_person_stock_and_records_distinct_giver_receiver_and_actor(): void
    {
        [$event, $actor, $engagement, $expected, $location] = $this->context();
        $item = $expected->entitlementItem;
        $holder = $this->seedHeldStock($item, $location, 2, $actor);
        $other = User::factory()->create();
        $this->seedHeldStock($item, $location, 7, $other);
        $this->seedStock($item, $location, 10, $actor);
        $this->assertFalse($event->teamEngagements()->where('person_id', $actor->person_id)->exists());

        $this->actingAs($actor)->post(route('check-in.issues.store', $expected), ['code' => '  BAND-42  '])
            ->assertRedirect()->assertSessionHasNoErrors();
        $issued = $expected->fresh()->issuedEntitlement;
        $this->assertFalse(Schema::hasColumn('issued_entitlements', 'location_id'));
        $this->assertSame('BAND-42', $issued->code);
        $this->assertDatabaseHas('stock_movements', [
            'type' => MovementType::GiveOut, 'quantity' => 1, 'from_holder_id' => $holder->id,
            'to_holder_id' => null, 'issued_entitlement_id' => $issued->id, 'actor_user_id' => $actor->id,
        ]);
        $this->assertSame(1, app(StockMovementService::class)->balance($item, $holder));
        $this->assertSame(10, app(EntitlementItemService::class)->balanceForLocation($item, $location->id));
        $this->assertFalse(StockHolder::where('event_id', $event->id)->where('person_id', $expected->passAssignment->person_id)->exists());
        $this->getJson($this->table($engagement, $expected))
            ->assertJsonPath('data.0.own_stock', 1)->assertJsonPath('data.0.can_consume', false)
            ->assertJsonPath('data.0.issued.giver', $actor->person->name)
            ->assertJsonPath('data.0.issued.receiver', $expected->passAssignment->person->name)
            ->assertJsonPath('data.0.issued.recorded_by', $actor->name)
            ->assertJsonMissingPath('data.0.locations')->assertJsonMissingPath('data.0.issued.location')
            ->assertJsonPath('data.0.issued.issued_by', $actor->name);
    }

    public function test_own_stock_only_is_returned_and_location_or_foreign_event_stock_is_never_a_fallback(): void
    {
        [$event, $actor, $engagement, $expected, $location] = $this->context();
        $this->seedStock($expected->entitlementItem, $location, 20, $actor);
        $other = User::factory()->create();
        $this->seedHeldStock($expected->entitlementItem, $location, 8, $other);
        $foreign = Event::create(['name' => 'Other', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        $foreignItem = $foreign->entitlementItems()->create(['name' => 'Wristband']);
        $this->seedHeldStock($foreignItem, $foreign->locations()->create(['name' => 'Other gate']), 6, $actor);
        $before = StockHolder::count();
        $this->actingAs($actor)->getJson($this->table($engagement, $expected))
            ->assertOk()->assertJsonPath('data.0.own_stock', 0)->assertJsonPath('data.0.can_consume', false)
            ->assertJsonPath('data.0.consume_blocked_reason', __('check_in.no_stock'))
            ->assertJsonMissingPath('data.0.locations');
        $this->assertSame($before, StockHolder::count());
        $this->postJson(route('check-in.issues.store', $expected), [])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame('expected', $expected->fresh()->status);
        $this->assertDatabaseCount('issued_entitlements', 0);
        $this->assertSame(0, $expected->entitlementItem->movements()->where('type', MovementType::GiveOut)->count());
    }

    public function test_inherited_inventory_permissions_can_consume_held_stock_and_keep_the_same_holder(): void
    {
        foreach (['checkin.edit', 'inventory.manage', 'inventory.pass_shift', 'inventory.pass_group'] as $permission) {
            [$event, $actor, $engagement, $expected, $location] = $this->context();
            $holder = $this->seedHeldStock($expected->entitlementItem, $location, 2, $actor);
            $this->grantRoleAccess($actor, [$permission]);
            $this->actingAs($actor)->getJson($this->table($engagement, $expected))
                ->assertOk()->assertJsonPath('data.0.can_consume', true);
            $this->post(route('check-in.issues.store', $expected), [])->assertSessionHasNoErrors();
            $this->assertSame($holder->id, $expected->fresh()->issuedEntitlement->giveOutMovement->from_holder_id);
            $this->assertSame(1, app(StockMovementService::class)->balance($expected->entitlementItem, $holder));
        }
    }

    public function test_inactive_holder_is_flagged_and_a_stale_authorized_actor_cannot_consume(): void
    {
        [$event, $actor, $engagement, $expected, $location] = $this->context();
        $holder = $this->seedHeldStock($expected->entitlementItem, $location, 2, $actor);
        $this->grantRoleAccess($actor, ['checkin.edit']);
        $member = $event->teamEngagements()->where('person_id', $actor->person_id)->sole();
        $member->role->update(['permissions' => ['checkin.view']]);
        $this->actingAs($actor)->getJson($this->table($engagement, $expected))
            ->assertOk()->assertJsonPath('data.0.own_stock', 2)->assertJsonPath('data.0.can_consume', false)
            ->assertJsonPath('data.0.consume_blocked_reason', __('check_in.inactive_stock'));
        $this->postJson(route('check-in.issues.store', $expected), [])->assertForbidden();
        try {
            app(EntitlementConsumeService::class)->consume($expected, $actor);
            $this->fail('The service must recheck revoked permissions.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(2, app(StockMovementService::class)->balance($expected->entitlementItem, $holder));
        $this->assertDatabaseCount('issued_entitlements', 0);
    }

    public function test_request_rejects_client_selected_sources_and_invalid_optional_codes(): void
    {
        [, $actor, , $expected, $location] = $this->context();
        $holder = $this->seedHeldStock($expected->entitlementItem, $location, 2, $actor);
        foreach ([['location_id' => $location->id], ['holder_id' => $holder->id], ['code' => []], ['code' => str_repeat('X', 256)]] as $data) {
            $this->actingAs($actor)->postJson(route('check-in.issues.store', $expected), $data)
                ->assertUnprocessable()->assertJsonValidationErrors(array_key_first($data));
        }
        $this->assertDatabaseCount('issued_entitlements', 0);
        $this->post(route('check-in.issues.store', $expected), ['code' => '  '])->assertSessionHasNoErrors();
        $this->assertNull($expected->fresh()->issuedEntitlement->code);
    }

    public function test_legacy_location_giver_and_different_recorder_remain_visible_and_sortable(): void
    {
        [$event, $actor, $engagement, $expected, $location] = $this->context();
        $this->seedStock($expected->entitlementItem, $location, 1, $actor);
        $issued = $expected->issuedEntitlement()->create(['entitlement_item_id' => $expected->entitlement_item_id, 'issued_by' => $actor->id, 'issued_at' => now()]);
        $expected->update(['status' => 'consumed']);
        app(StockMovementService::class)->move($expected->entitlementItem, MovementType::GiveOut, 1,
            StockHolder::where('location_id', $location->id)->sole(), null, $actor, issued: $issued);
        foreach ([3, 4, 5, 6] as $column) {
            $this->actingAs($actor)->getJson($this->table($engagement, $expected, ['order' => [['column' => $column, 'dir' => 'desc']]]))
                ->assertOk()->assertJsonPath('data.0.issued.giver', $location->name)
                ->assertJsonPath('data.0.issued.recorded_by', $actor->name);
        }
        $this->getJson($this->table($engagement, $expected, ['search' => ['value' => $location->name]]))
            ->assertJsonPath('recordsFiltered', 1);
    }

    public function test_location_drop_refuses_unrepresented_history_and_preserves_linked_history(): void
    {
        [$event, $actor, , $expected, $location] = $this->context();
        $migration = require database_path('migrations/2026_10_17_000002_remove_location_from_issued_entitlements.php');
        $migration->down();
        $issued = $expected->issuedEntitlement()->create([
            'entitlement_item_id' => $expected->entitlement_item_id, 'code' => 'HISTORY', 'issued_by' => $actor->id,
        ]);
        DB::table('issued_entitlements')->where('id', $issued->id)->update(['location_id' => $location->id]);
        try {
            $migration->up();
            $this->fail('Unrepresented history must prevent a destructive column drop.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('history', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('issued_entitlements', 'location_id'));
        $this->seedStock($expected->entitlementItem, $location, 1, $actor);
        app(StockMovementService::class)->move($expected->entitlementItem, MovementType::GiveOut, 1,
            StockHolder::where('location_id', $location->id)->sole(), null, $actor, issued: $issued);
        $before = DB::table('stock_movements')->orderBy('id')->get()->toJson();
        $migration->up();
        $this->assertFalse(Schema::hasColumn('issued_entitlements', 'location_id'));
        $this->assertSame($before, DB::table('stock_movements')->orderBy('id')->get()->toJson());
        $this->assertSame($location->name, $issued->fresh()->giveOutMovement->fromHolder->location->name);
        $this->assertSame('HISTORY', $issued->fresh()->code);
        try {
            $migration->down();
            $this->fail('Rollback cannot invent source locations after check-in has been used.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('backup', $exception->getMessage());
        }
    }

    private function context(): array
    {
        $event = Event::create(['name' => 'Held stock', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        $actor = User::factory()->create();
        $this->grantAdminAccess($actor);
        $actor->setCurrentEvent($event);
        app(OrganizationContext::class)->setDefaultEvent($event);
        app(OrganizationContext::class)->markSetupComplete();
        $engagement = ArtistEngagement::factory()->for($event)->create(['status' => 'confirmed']);
        $person = Person::create(['name' => 'Receiver '.fake()->uuid(), 'email' => fake()->unique()->safeEmail()]);
        $engagement->people()->attach($person);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $pass = $event->passTypes()->create(['name' => 'Artist pass']);
        $assignment = $engagement->passAssignments()->create(['person_id' => $person->id, 'pass_type_id' => $pass->id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);

        return [$event, $actor, $engagement, $expected, $event->locations()->create(['name' => 'Main gate'])];
    }

    private function table(ArtistEngagement $engagement, ExpectedEntitlement $expected, array $filters = []): string
    {
        return route('check-in.entitlements', [...[
            'draw' => 1, 'start' => 0, 'length' => 25, 'type' => 'artist',
            'engagement_id' => $engagement->id, 'person_id' => $expected->passAssignment->person_id,
        ], ...$filters]);
    }
}
