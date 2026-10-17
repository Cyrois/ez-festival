<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistEngagement;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\Person;
use App\Models\User;
use App\Services\EntitlementConsumeService;
use App\Services\EntitlementItemService;
use App\Support\EntitlementCode;
use App\Support\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UniqueEntitlementCodesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_code_length_limit_applies_to_coded_and_optional_items_without_partial_writes(): void
    {
        foreach ([true, false] as $needsUniqueCode) {
            [$event, $actor, $item, $location] = $this->context();
            $item->update(['needs_unique_code' => $needsUniqueCode]);
            $expected = $this->expected($event, $item);
            $tooLong = str_repeat('X', 65);
            $this->actingAs($actor)->postJson(route('check-in.issues.store', $expected), [
                'location_id' => $location->id, 'code' => $tooLong,
            ])->assertUnprocessable()->assertJsonValidationErrors('code');
            try {
                app(EntitlementConsumeService::class)->consume($expected, $actor, $location->id, $tooLong);
                $this->fail('The service must reject codes longer than 64 characters.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('code', $exception->errors());
            }
            $this->assertNull($expected->fresh()->issuedEntitlement);
            $this->assertSame('expected', $expected->fresh()->status);
            $this->assertFalse(EntitlementCode::isUsed($event->id, $tooLong));
            $this->assertSame(1, $item->movements()->count());

            $code = str_repeat('X', 64);
            $this->post(route('check-in.issues.store', $expected), [
                'location_id' => $location->id, 'code' => $code,
            ])->assertSessionHasNoErrors();
            $this->assertSame($code, $expected->fresh()->issuedEntitlement->code);
        }
    }

    public function test_coded_item_requires_a_valid_code_through_the_form_request(): void
    {
        [$event, $actor, $item, $location] = $this->context();
        $expected = $this->expected($event, $item);
        foreach ([null, '', '   ', [], 123, str_repeat('X', 65)] as $code) {
            $this->actingAs($actor)->postJson(route('check-in.issues.store', $expected), [
                'location_id' => $location->id, 'code' => $code,
            ])->assertUnprocessable()->assertJsonValidationErrors('code');
        }
        $this->assertDatabaseCount('issued_entitlements', 0);
        $this->assertDatabaseCount('entitlement_code_reservations', 0);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    #[DataProvider('duplicateCodes')]
    public function test_duplicate_code_is_refused_across_the_event_without_recording_anything(string $code, bool $differentItem): void
    {
        [$event, $actor, $item, $location] = $this->context();
        $service = app(EntitlementConsumeService::class);
        $first = $this->expected($event, $item);
        $issued = $service->consume($first, $actor, $location->id, ' CODE-42 ');
        $secondItem = $differentItem ? $event->entitlementItems()->create(['name' => 'Lanyard', 'needs_unique_code' => true]) : $item;
        $second = $this->expected($event, $secondItem);
        $this->seedStock($secondItem, $location, 2, $actor);
        $before = DB::table('stock_movements')->count();
        $this->actingAs($actor)->postJson(route('check-in.issues.store', $second), [
            'location_id' => $location->id, 'code' => $code,
        ])->assertUnprocessable()->assertJsonValidationErrors('code')
            ->assertJsonPath('errors.code.0', __('check_in.errors.code_used'));
        $this->assertSame('CODE-42', $issued->code);
        $this->assertDatabaseCount('issued_entitlements', 1);
        $this->assertDatabaseCount('stock_movements', $before);
        $this->assertDatabaseCount('entitlement_code_reservations', 1);
        $this->assertSame(ExpectedEntitlement::STATUS_EXPECTED, $second->fresh()->status);
    }

    public static function duplicateCodes(): array
    {
        return [
            'same item' => ['CODE-42', false], 'different item' => ['CODE-42', true],
            'case' => ['code-42', false], 'case across items' => ['code-42', true],
            'spaces' => [' CODE-42 ', false], 'spaces across items' => [' code-42 ', true],
        ];
    }

    public function test_optional_codes_stay_optional_and_repeated_but_count_as_used_for_coded_items(): void
    {
        [$event, $actor, $item, $location] = $this->context();
        $item->update(['needs_unique_code' => false]);
        $service = app(EntitlementConsumeService::class);
        $this->seedStock($item, $location, 5, $actor);
        foreach ([null, 'FREE-1', ' free-1 '] as $code) {
            $service->consume($this->expected($event, $item), $actor, $location->id, $code);
        }
        $this->assertDatabaseCount('issued_entitlements', 3);
        $this->assertDatabaseCount('entitlement_code_reservations', 1);
        $item->update(['needs_unique_code' => true]);
        $expected = $this->expected($event, $item);
        $this->actingAs($actor)->postJson(route('check-in.issues.store', $expected), [
            'location_id' => $location->id, 'code' => 'FREE-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_same_code_can_be_issued_in_another_event(): void
    {
        [$event, $actor, $item, $location] = $this->context();
        $service = app(EntitlementConsumeService::class);
        $service->consume($this->expected($event, $item), $actor, $location->id, 'EVENT-1');
        [$other, , $otherItem, $otherLocation] = $this->context();
        $service->consume($this->expected($other, $otherItem), $actor, $otherLocation->id, 'EVENT-1');
        $this->assertDatabaseCount('issued_entitlements', 2);
        $this->assertDatabaseCount('entitlement_code_reservations', 2);
    }

    public function test_service_rechecks_current_setting_and_duplicate_and_rolls_back_reservations_on_stock_failure(): void
    {
        [$event, $actor, $item, $location] = $this->context();
        $service = app(EntitlementConsumeService::class);
        $expected = $this->expected($event, $item);
        $expected->load('entitlementItem');
        foreach ([null, ' ', str_repeat('X', 65)] as $code) {
            try {
                $service->consume($expected, $actor, $location->id, $code);
                $this->fail('The service must require a valid code.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('code', $exception->errors());
            }
        }
        $service->consume($expected, $actor, $location->id, 'USED');
        $next = $this->expected($event, $item);
        try {
            $service->consume($next, $actor, $location->id, 'used');
            $this->fail('Direct service calls must refuse duplicate codes.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('check_in.errors.code_used')], $exception->errors()['code']);
        }
        $empty = $event->locations()->create(['name' => 'Empty']);
        try {
            $service->consume($next, $actor, $empty->id, 'STILL-FREE');
            $this->fail('Consumption from empty stock must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $this->assertFalse(EntitlementCode::isUsed($event->id, 'STILL-FREE'));
        $service->consume($next, $actor, $location->id, 'STILL-FREE');
        $this->assertDatabaseCount('issued_entitlements', 2);
    }

    public function test_toggling_setting_preserves_used_codes_and_stale_models_follow_the_new_setting(): void
    {
        [$event, $actor, $item, $location] = $this->context();
        $service = app(EntitlementConsumeService::class);
        $service->consume($this->expected($event, $item), $actor, $location->id, 'TAKEN');
        $expected = $this->expected($event, $item);
        $item->update(['needs_unique_code' => false]);
        $expected->load('entitlementItem');
        $item->update(['needs_unique_code' => true]);
        foreach ([null, 'taken'] as $code) {
            try {
                $service->consume($expected, $actor, $location->id, $code);
                $this->fail('A stale model must not bypass current code requirements.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('code', $exception->errors());
            }
        }
        $this->assertTrue(EntitlementCode::isUsed($event->id, 'TAKEN'));
        $item->update(['needs_unique_code' => false]);
        $service->consume($expected, $actor, $location->id);
    }

    public function test_item_setting_round_trips_invalidates_cache_and_checks_permissions_locks_and_event_scope(): void
    {
        [$event, $actor, $item] = $this->context();
        $items = app(EntitlementItemService::class);
        $this->assertTrue($items->list($event)->find($item->id)->needs_unique_code);
        $this->actingAs($actor)->putJson(route('credentials.entitlements.update', [$event, $item]), [
            'name' => $item->name, 'needs_unique_code' => false,
        ])->assertRedirect();
        $this->assertFalse($items->list($event)->find($item->id)->needs_unique_code);
        $this->get(route('credentials.entitlements.show', $item))->assertInertia(fn (Assert $page) => $page->where('item.needs_unique_code', false));
        $this->putJson(route('credentials.entitlements.update', [$event, $item]), [
            'name' => $item->name, 'needs_unique_code' => 'yes',
        ])->assertUnprocessable()->assertJsonValidationErrors('needs_unique_code');
        $this->grantRoleAccess($actor, ['credentials.stock.adjust']);
        $this->putJson(route('credentials.entitlements.update', [$event, $item]), [
            'name' => $item->name, 'needs_unique_code' => true,
        ])->assertForbidden();
        $this->grantAdminAccess($actor);
        [$other] = $this->context();
        $this->putJson(route('credentials.entitlements.update', [$other, $item]), [
            'name' => $item->name, 'needs_unique_code' => true,
        ])->assertNotFound();
        $event->lock();
        $this->putJson(route('credentials.entitlements.update', [$event, $item]), [
            'name' => $item->name, 'needs_unique_code' => true,
        ])->assertForbidden();
        $this->assertFalse($item->fresh()->needs_unique_code);
    }

    public function test_check_in_payload_exposes_setting_and_new_items_default_to_uncoded(): void
    {
        [$event, $actor, $item] = $this->context();
        $expected = $this->expected($event, $item);
        $assignment = $expected->passAssignment;
        $this->actingAs($actor)->getJson(route('check-in.entitlements', [
            'type' => 'artist', 'engagement_id' => $assignment->artist_engagement_id,
            'person_id' => $assignment->person_id, 'draw' => 1, 'start' => 0, 'length' => 25,
        ]))->assertOk()->assertJsonPath('data.0.needs_unique_code', true);
        $this->postJson(route('credentials.entitlements.store', $event), [
            'name' => 'New item', 'needs_unique_code' => true,
        ])->assertRedirect();
        $this->assertFalse($event->entitlementItems()->where('name', 'New item')->sole()->needs_unique_code);
    }

    public function test_consume_preserves_permission_lock_foreign_event_and_already_consumed_gates(): void
    {
        [$event, $actor, $item, $location] = $this->context();
        $expected = $this->expected($event, $item);
        $this->grantRoleAccess($actor, ['checkin.view']);
        $data = ['location_id' => $location->id, 'code' => 'GATED'];
        $this->actingAs($actor)->postJson(route('check-in.issues.store', $expected), $data)->assertForbidden();
        $this->grantAdminAccess($actor);
        [$other, , $foreignItem] = $this->context();
        $foreign = $this->expected($other, $foreignItem);
        $this->postJson(route('check-in.issues.store', $foreign), $data)->assertNotFound();
        $event->lock();
        $this->postJson(route('check-in.issues.store', $expected), $data)->assertForbidden();
        $this->assertDatabaseCount('entitlement_code_reservations', 0);
        $event->unlock();
        $this->postJson(route('check-in.issues.store', $expected), $data)->assertRedirect();
        $this->postJson(route('check-in.issues.store', $expected), [...$data, 'code' => 'NEW'])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_entitlement');
        $this->assertFalse(EntitlementCode::isUsed($event->id, 'NEW'));
    }

    public function test_migration_leaves_existing_codes_unreserved_and_preserves_history_on_rollback(): void
    {
        [$event, $actor, $item, $location] = $this->context();
        $migration = require database_path('migrations/2026_10_17_000001_add_unique_entitlement_codes.php');
        $migration->down();
        foreach ([' Legacy-1 ', 'legacy-1', null, '   '] as $code) {
            $this->expected($event, $item)->issuedEntitlement()->create([
                'entitlement_item_id' => $item->id, 'code' => $code, 'issued_by' => $actor->id,
            ]);
        }
        $before = DB::table('issued_entitlements')->orderBy('id')->get()->toJson();
        $migration->up();
        $this->assertFalse($item->fresh()->needs_unique_code);
        $this->assertDatabaseCount('entitlement_code_reservations', 0);
        $this->assertFalse(EntitlementCode::isUsed($event->id, 'LEGACY-1'));
        $this->assertSame($before, DB::table('issued_entitlements')->orderBy('id')->get()->toJson());
        $item->refresh()->update(['needs_unique_code' => true]);
        $this->actingAs($actor)->postJson(route('check-in.issues.store', $this->expected($event, $item)), [
            'location_id' => $location->id, 'code' => 'LEGACY-1',
        ])->assertRedirect();
        $this->assertTrue(EntitlementCode::isUsed($event->id, 'LEGACY-1'));
        $this->assertDatabaseCount('entitlement_code_reservations', 1);
        $beforeRollback = DB::table('issued_entitlements')->orderBy('id')->get()->toJson();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('entitlement_items', 'needs_unique_code'));
        $this->assertSame($beforeRollback, DB::table('issued_entitlements')->orderBy('id')->get()->toJson());
    }

    public function test_database_enforces_one_reservation_per_event_and_code(): void
    {
        [$event] = $this->context();
        $this->assertTrue(EntitlementCode::reserve($event->id, 'ONCE'));
        try {
            DB::transaction(fn () => DB::table('entitlement_code_reservations')->insert([
                'event_id' => $event->id, 'code_key' => 'once',
            ]));
            $this->fail('The database must reject a duplicate reservation.');
        } catch (QueryException) {
            $this->assertDatabaseCount('entitlement_code_reservations', 1);
        }
    }

    private function context(): array
    {
        $event = Event::create(['name' => 'Code event', 'starts_on' => '2026-10-17', 'ends_on' => '2026-10-19', 'timezone' => 'America/Vancouver']);
        $actor = User::factory()->create();
        $this->grantAdminAccess($actor);
        $actor->setCurrentEvent($event);
        app(OrganizationContext::class)->setDefaultEvent($event);
        app(OrganizationContext::class)->markSetupComplete();
        $item = $event->entitlementItems()->create(['name' => 'Wristband', 'needs_unique_code' => true]);
        $location = $event->locations()->create(['name' => 'Gate']);
        $this->seedStock($item, $location, 2, $actor);

        return [$event, $actor, $item, $location];
    }

    private function expected(Event $event, EntitlementItem $item): ExpectedEntitlement
    {
        $engagement = ArtistEngagement::factory()->for($event)->for(Artist::factory())->create(['status' => 'confirmed']);
        $person = Person::create(['name' => fake()->name(), 'email' => fake()->unique()->safeEmail()]);
        $engagement->people()->attach($person, ['is_primary' => true]);
        $pass = $event->passTypes()->firstOrCreate(['name' => 'Artist pass']);
        $assignment = $engagement->passAssignments()->create(['pass_type_id' => $pass->id, 'person_id' => $person->id]);

        return $assignment->expectedEntitlements()->create(['entitlement_item_id' => $item->id]);
    }
}
