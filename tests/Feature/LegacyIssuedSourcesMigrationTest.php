<?php

namespace Tests\Feature;

use App\Enums\StockMovement as MovementType;
use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Person;
use App\Models\StockHolder;
use App\Models\User;
use App\Services\EventLocationService;
use App\Services\EventService;
use App\Services\StockMovementService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegacyIssuedSourcesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_preserves_legacy_sources_without_debiting_stock_and_exposes_them_in_audit(): void
    {
        [$event, $actor, $engagement, $expected, $location, $issued] = $this->legacyContext();
        $stockLocation = $event->locations()->create(['name' => 'Current stock']);
        $this->seedStock($expected->entitlementItem, $stockLocation, 8, $actor);
        $ledger = DB::table('stock_movements')->orderBy('id')->get()->toJson();
        $issuedBefore = DB::table('issued_entitlements')->where('id', $issued->id)->first();
        $this->compatibility()->up();
        $this->assertDatabaseHas('legacy_issued_entitlement_sources', ['issued_entitlement_id' => $issued->id, 'location_id' => $location->id]);
        $this->assertNull(DB::table('issued_entitlements')->where('id', $issued->id)->value('location_id'));
        $this->removal()->up();
        $this->assertFalse(Schema::hasColumn('issued_entitlements', 'location_id'));
        $this->assertSame($ledger, DB::table('stock_movements')->orderBy('id')->get()->toJson());
        $this->assertSame(8, app(StockMovementService::class)->total($expected->entitlementItem));
        $this->assertDatabaseMissing('stock_holders', ['location_id' => $location->id]);
        $after = DB::table('issued_entitlements')->where('id', $issued->id)->first();
        unset($issuedBefore->location_id);
        $this->assertEquals($issuedBefore, $after);
        $this->assertSame($location->id, $issued->fresh()->legacySource->location_id);
        $params = ['draw' => 1, 'start' => 0, 'length' => 25, 'type' => 'artist',
            'engagement_id' => $engagement->id, 'person_id' => $expected->passAssignment->person_id];
        $this->actingAs($actor)->getJson(route('check-in.entitlements', $params))->assertOk()
            ->assertJsonPath('data.0.issued.giver', $location->name)
            ->assertJsonPath('data.0.issued.recorded_by', null)
            ->assertJsonPath('data.0.issued.issued_by', $actor->name)
            ->assertJsonPath('data.0.issued.code', 'LEGACY-42');
        $this->getJson(route('check-in.entitlements', [...$params, 'search' => ['value' => $location->name], 'order' => [['column' => 3, 'dir' => 'desc']]]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.issued.giver', $location->name);
        $this->getJson(route('check-in.history.data'))->assertOk()->assertJsonPath('recordsTotal', 1);
        try {
            $this->compatibility()->down();
            $this->fail('Removing archived sources must refuse a lossy rollback.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('preserved', $error->getMessage());
        }
        $this->assertDatabaseHas('legacy_issued_entitlement_sources', ['issued_entitlement_id' => $issued->id]);
    }

    public function test_legacy_location_deletion_is_blocked_before_fk_and_event_deletion_clears_sources(): void
    {
        [$event, $actor, , , $location, $issued] = $this->legacyContext();
        $this->compatibility()->up();
        $this->removal()->up();
        $this->actingAs($actor)->delete(route('settings.events.locations.destroy', [$event, $location]))
            ->assertSessionHasErrors('location');
        try {
            app(EventLocationService::class)->destroy($event, $location);
            $this->fail('The service must refuse removal of legacy source history.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('location', $error->errors());
        }
        $this->assertDatabaseHas('legacy_issued_entitlement_sources', ['issued_entitlement_id' => $issued->id]);
        app(EventService::class)->delete($event);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseMissing('legacy_issued_entitlement_sources', ['issued_entitlement_id' => $issued->id]);
    }

    public function test_sources_can_be_restored_if_compatibility_is_rolled_back_before_the_column_drop(): void
    {
        [, , , , $location, $issued] = $this->legacyContext();
        $this->compatibility()->up();
        $this->compatibility()->down();
        $this->assertSame($location->id, (int) DB::table('issued_entitlements')->where('id', $issued->id)->value('location_id'));
        $this->compatibility()->up();
        $this->removal()->up();
        $this->assertDatabaseHas('legacy_issued_entitlement_sources', ['issued_entitlement_id' => $issued->id, 'location_id' => $location->id]);
    }

    public function test_valid_linked_history_is_not_archived_or_changed(): void
    {
        [$event, $actor, , $expected, $location, $issued] = $this->legacyContext();
        $this->seedStock($expected->entitlementItem, $location, 2, $actor);
        $holder = StockHolder::where('location_id', $location->id)->sole();
        app(StockMovementService::class)->move($expected->entitlementItem, MovementType::GiveOut, 1, $holder, null, $actor, issued: $issued);
        $ledger = DB::table('stock_movements')->orderBy('id')->get()->toJson();
        $this->compatibility()->up();
        $this->assertDatabaseCount('legacy_issued_entitlement_sources', 0);
        $this->assertSame($location->id, (int) DB::table('issued_entitlements')->where('id', $issued->id)->value('location_id'));
        $this->removal()->up();
        $this->assertSame($ledger, DB::table('stock_movements')->orderBy('id')->get()->toJson());
        $this->assertSame(1, app(StockMovementService::class)->total($expected->entitlementItem));
    }

    public function test_conflicting_movement_sources_are_not_silently_archived(): void
    {
        [$event, $actor, , $expected, $location, $issued] = $this->legacyContext();
        $source = $this->seedHeldStock($expected->entitlementItem, $location, 2, $actor);
        app(StockMovementService::class)->move($expected->entitlementItem, MovementType::GiveOut, 1, $source, null, $actor, issued: $issued);
        try {
            $this->compatibility()->up();
            $this->fail('A different linked giver needs reconciliation, not silent replacement.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('conflict', $error->getMessage());
        }
        $this->assertSame($location->id, (int) DB::table('issued_entitlements')->where('id', $issued->id)->value('location_id'));
        $this->assertFalse(Schema::hasTable('legacy_issued_entitlement_sources'));
    }

    public function test_already_upgraded_databases_get_an_empty_legacy_source_table_without_data_changes(): void
    {
        $before = DB::table('issued_entitlements')->get()->toJson();
        $this->compatibility()->down();
        $this->compatibility()->up();
        $this->assertFalse(Schema::hasColumn('issued_entitlements', 'location_id'));
        $this->assertDatabaseCount('legacy_issued_entitlement_sources', 0);
        $this->assertSame($before, DB::table('issued_entitlements')->get()->toJson());
    }

    private function legacyContext(): array
    {
        $this->withoutVite();
        $this->compatibility()->down();
        $this->removal()->down();
        $event = Event::create(['name' => 'Legacy festival', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        $actor = $this->grantAdminAccess(User::factory()->create());
        $actor->setCurrentEvent($event);
        app(OrganizationContext::class)->setDefaultEvent($event);
        app(OrganizationContext::class)->markSetupComplete();
        $engagement = ArtistEngagement::factory()->for($event)->create(['status' => 'confirmed']);
        $person = Person::create(['name' => 'Legacy recipient', 'email' => 'legacy-recipient@example.test']);
        $engagement->people()->attach($person);
        $item = $event->entitlementItems()->create(['name' => 'Wristband']);
        $pass = $event->passTypes()->create(['name' => 'Artist pass']);
        $assignment = $engagement->passAssignments()->create(['person_id' => $person->id, 'pass_type_id' => $pass->id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id, 'status' => 'consumed']);
        $location = $event->locations()->create(['name' => 'Legacy gate']);
        $issued = $expected->issuedEntitlement()->create(['entitlement_item_id' => $item->id, 'code' => 'LEGACY-42', 'issued_by' => $actor->id, 'issued_at' => now()]);
        DB::table('issued_entitlements')->where('id', $issued->id)->update(['location_id' => $location->id]);

        return [$event, $actor, $engagement, $expected, $location, $issued];
    }

    private function compatibility(): object
    {
        return require database_path('migrations/2026_10_17_000001_preserve_legacy_issued_sources.php');
    }

    private function removal(): object
    {
        return require database_path('migrations/2026_10_17_000002_remove_location_from_issued_entitlements.php');
    }
}
