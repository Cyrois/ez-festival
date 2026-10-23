<?php

namespace Tests\Feature;

use App\Enums\StockMovement as MovementType;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\Location;
use App\Models\Person;
use App\Models\StockHolder;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\EntitlementItemService;
use App\Services\StockMovementService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $manager;

    private User $staff;

    private Location $location;

    private EntitlementItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->event = Event::create(['name' => 'Festival', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-04', 'timezone' => 'America/Vancouver']);
        $this->manager = $this->grantRoleAccess(User::factory()->create(), ['inventory.manage']);
        $this->staff = $this->grantRoleAccess(User::factory()->create(), ['checkin.edit']);
        $this->location = $this->event->locations()->create(['name' => 'Gate']);
        $this->item = $this->event->entitlementItems()->create(['name' => 'Wristband']);
        app(OrganizationContext::class)->setDefaultEvent($this->event);
        app(OrganizationContext::class)->markSetupComplete();
        $this->actingAs($this->manager);
    }

    public function test_history_has_distinct_giver_recipient_recorder_note_code_and_labels_for_every_type(): void
    {
        $stock = app(StockMovementService::class);
        $this->seedHeldStock($this->item, $this->location, 20, $this->staff);
        $source = StockHolder::where('person_id', $this->staff->person_id)->sole();
        $target = $stock->forPerson($this->event, $this->manager->person);
        $location = StockHolder::where('location_id', $this->location->id)->sole();
        $stock->move($this->item, MovementType::Transfer, 2, $source, $target, $this->manager, 'Passed on');
        $stock->move($this->item, MovementType::TakeBack, 1, $source, $target, $this->manager, 'End of shift');
        $stock->move($this->item, MovementType::Return, 1, $source, $location, $this->manager);
        $stock->move($this->item, MovementType::Remove, 1, $location, null, $this->manager, 'Damaged');
        [$recipient, $giveOut] = $this->giveOut($source, 'BAND-42');
        $label = $this->event->entitlementItemLabels()->create(['name' => 'Artist', 'color' => 'teal']);
        $this->item->labels()->attach($label);
        $rows = collect($this->getJson(route('check-in.history.data'))->assertOk()->json('data'));
        $this->assertEqualsCanonicalizing(array_column(MovementType::cases(), 'value'), $rows->pluck('type')->all());
        $issued = $rows->firstWhere('id', $giveOut->id);
        $this->assertSame($this->staff->person->name, $issued['giver']);
        $this->assertSame($recipient->name, $issued['receiver']);
        $this->assertSame($this->manager->name, $issued['recorded_by']);
        $this->assertSame('BAND-42', $issued['code']);
        $this->assertSame('Artist', $issued['labels'][0]['name']);
        $this->assertNull($rows->firstWhere('type', 'add')['giver']);
        $this->assertNull($rows->firstWhere('type', 'remove')['receiver']);
        $this->assertSame('Damaged', $rows->firstWhere('type', 'remove')['note']);
        $this->assertDatabaseMissing('stock_holders', ['person_id' => $recipient->id]);
    }

    public function test_each_filter_and_combined_filters_use_event_scoped_history_including_check_in_recipients(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 3)->setTime(10, 0));
        $holder = $this->seedHeldStock($this->item, $this->location, 2, $this->staff);
        [$recipient, $movement] = $this->giveOut($holder, '42');
        $otherItem = $this->event->entitlementItems()->create(['name' => 'Voucher']);
        $this->seedStock($otherItem, $this->location, 3, $this->manager);
        foreach ([['item_id' => $otherItem->id], ['holder' => 'person:'.$recipient->id], ['recorded_by' => $this->manager->id], ['type' => 'give_out'],
            ['item_id' => $this->item->id, 'holder' => 'person:'.$recipient->id, 'recorded_by' => $this->manager->id, 'type' => 'give_out', 'start_date' => '2026-10-03', 'end_date' => '2026-10-03']] as $filters) {
            $response = $this->getJson(route('check-in.history.data', $filters))->assertOk();
            if (isset($filters['holder']) || isset($filters['type'])) {
                $response->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.id', $movement->id);
            } elseif (isset($filters['item_id'])) {
                $response->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.item', 'Voucher');
            } else {
                $response->assertJsonPath('recordsFiltered', 2);
            }
        }
        $this->getJson(route('check-in.history.data', ['holder' => 'location:'.$this->location->id]))->assertOk()->assertJsonPath('recordsFiltered', 3);
        $this->getJson(route('check-in.history.options', ['option' => 'holder', 'search' => $recipient->name]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.value', 'person:'.$recipient->id);
    }

    public function test_date_range_uses_whole_event_local_days_including_dst_and_not_the_event_date_limits(): void
    {
        $this->travelTo('2026-11-01 06:59:59 UTC');
        $before = $this->seedStock($this->item, $this->location, 1);
        $this->travelTo('2026-11-01 07:00:00 UTC');
        $first = $this->seedStock($this->item, $this->location, 1);
        $this->travelTo('2026-11-02 07:59:59 UTC');
        $last = $this->seedStock($this->item, $this->location, 1);
        $this->travelTo('2026-11-02 08:00:00 UTC');
        $after = $this->seedStock($this->item, $this->location, 1);
        $ids = $this->getJson(route('check-in.history.data', ['start_date' => '2026-11-01', 'end_date' => '2026-11-01']))
            ->assertOk()->assertJsonPath('recordsFiltered', 2)->json('data.*.id');
        $this->assertSame([$last->id, $first->id], $ids);
        $this->assertNotContains($before->id, $ids);
        $this->assertNotContains($after->id, $ids);
    }

    public function test_server_paging_defaults_to_ten_and_refuses_other_page_sizes(): void
    {
        for ($i = 0; $i < 55; $i++) {
            $this->seedStock($this->item, $this->location, 1);
        }
        $response = $this->getJson(route('check-in.history.data'))->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('recordsTotal', 55);
        $ids = $response->json('data.*.id');
        $this->assertSame(StockMovement::orderByDesc('id')->limit(10)->pluck('id')->all(), $ids);
        foreach ([10, 20, 50] as $length) {
            $this->getJson(route('check-in.history.data', ['start' => 2, 'length' => $length]))->assertOk()->assertJsonCount($length, 'data');
        }
        foreach ([-1, 0, 25, 51, 100] as $length) {
            $this->getJson(route('check-in.history.data', ['length' => $length]))->assertUnprocessable()->assertJsonValidationErrors('length');
        }
    }

    public function test_permissions_isolate_item_history_options_and_people_from_event_wide_data(): void
    {
        $this->seedHeldStock($this->item, $this->location, 2, $this->staff);
        $other = $this->event->entitlementItems()->create(['name' => 'Private item']);
        $outsider = User::factory()->create();
        $this->seedHeldStock($other, $this->location, 4, $outsider);
        foreach (['credentials.entitlements.edit', 'credentials.stock.adjust'] as $permission) {
            $viewer = $this->grantRoleAccess(User::factory()->create(), [$permission]);
            $this->actingAs($viewer);
            foreach (['index', 'data', 'options', 'export'] as $action) {
                $this->getJson(route('check-in.history.'.$action))->assertForbidden();
            }
            $this->get(route('credentials.entitlements.show', $this->item))->assertInertia(fn (Assert $p) => $p
                ->where('history_export_url', null)->where('stats.held_by_people', 2)->missing('history'));
            $this->getJson(route('credentials.entitlements.history', $this->item))->assertOk()->assertJsonPath('recordsTotal', 2);
            $this->getJson(route('credentials.entitlements.holdings', $this->item))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->staff->person_id);
            $this->getJson(route('credentials.entitlements.history.options', [$this->item, 'option' => 'holder']))->assertOk()->assertJsonMissing(['value' => 'person:'.$outsider->person_id]);
            $this->getJson(route('credentials.entitlements.history', [$this->item, 'item_id' => $other->id]))->assertUnprocessable()->assertJsonValidationErrors('item_id');
        }
        $this->actingAs($this->staff)->getJson(route('credentials.entitlements.history', $this->item))->assertForbidden();
        $this->getJson(route('credentials.entitlements.holdings', $this->item))->assertForbidden();
        $this->getJson(route('check-in.inventory.movements', ['holder' => 'person:'.$outsider->person_id]))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_foreign_event_and_malformed_filters_are_rejected(): void
    {
        $foreign = Event::create(['name' => 'Other', 'starts_on' => '2027-01-01', 'ends_on' => '2027-01-02', 'timezone' => 'UTC']);
        $item = $foreign->entitlementItems()->create(['name' => 'Foreign']);
        $location = $foreign->locations()->create(['name' => 'Secret']);
        $actor = User::factory()->create();
        $this->seedStock($item, $location, 4, $actor);
        foreach ([['item_id' => $item->id], ['holder' => 'location:'.$location->id], ['recorded_by' => $actor->id], ['holder' => 'person:-1'], ['type' => 'invented'],
            ['start' => -1], ['start_date' => '2026-10-02'], ['end_date' => '2026-10-02'], ['start_date' => '2026-10-03', 'end_date' => '2026-10-02'], ['start_date' => 'bad', 'end_date' => 'bad']] as $filters) {
            $this->getJson(route('check-in.history.data', $filters))->assertUnprocessable();
            $this->getJson(route('check-in.history.export', $filters))->assertUnprocessable();
        }
        foreach (['history', 'history.options', 'holdings'] as $route) {
            $this->getJson(route('credentials.entitlements.'.$route, $item))->assertNotFound();
        }
        $this->getJson(route('check-in.history.options'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_item_people_totals_and_flags_are_complete_batched_and_paged(): void
    {
        $this->seedStock($this->item, $this->location, 8);
        $this->seedHeldStock($this->item, $this->location, 3, $this->staff);
        $admin = $this->grantAdminAccess(User::factory()->create());
        $this->seedHeldStock($this->item, $this->location, 2, $admin);
        $this->event->teamEngagements()->where('person_id', $this->staff->person_id)->first()->role->update(['permissions' => []]);
        $rows = collect($this->getJson(route('credentials.entitlements.holdings', $this->item))->assertOk()->json('data'));
        $this->assertTrue($rows->firstWhere('id', $this->staff->person_id)['inactive']);
        $this->assertFalse($rows->firstWhere('id', $admin->person_id)['inactive']);
        $this->get(route('credentials.entitlements.show', $this->item))->assertInertia(fn (Assert $p) => $p
            ->where('stats.in_stock', 13)->where('stats.held_by_people', 5)->where('locations.0.in_stock', 8));
        for ($i = 0; $i < 12; $i++) {
            $this->seedHeldStock($this->item, $this->location, 1, User::factory()->create());
        }
        $this->getJson(route('credentials.entitlements.holdings', $this->item))->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('recordsTotal', 14);
        $this->getJson(route('credentials.entitlements.holdings', [$this->item, 'start' => 10]))->assertJsonCount(4, 'data');
    }

    public function test_csv_uses_all_filtered_rows_event_timezone_notes_codes_and_safe_text(): void
    {
        $this->travelTo('2026-10-03 08:00:00 UTC');
        $this->item->update(['name' => '=1+1']);
        for ($i = 0; $i < 505; $i++) {
            $this->seedStock($this->item, $this->location, 1, $this->manager);
        }
        $response = $this->get(route('check-in.history.export', ['item_id' => $this->item->id, 'type' => 'add', 'length' => 10, 'start' => 500]))->assertOk();
        $csv = $this->csv($response->streamedContent());
        $this->assertCount(506, $csv);
        $this->assertSame('2026-10-03 01:00:00 -07:00', $csv[1][0]);
        $this->assertSame("'=1+1", $csv[1][2]);
        $this->assertSame('—', $csv[1][4]);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $holder = $this->seedHeldStock($this->item, $this->location, 1, $this->staff);
        [$recipient] = $this->giveOut($holder, 'CODE-1');
        $csv = $this->csv($this->get(route('check-in.history.export', ['holder' => 'person:'.$recipient->id]))->streamedContent());
        $this->assertCount(2, $csv);
        $this->assertSame($recipient->name.' · CODE-1', $csv[1][5]);
        $this->assertSame($this->manager->name, $csv[1][6]);
    }

    public function test_locked_history_and_export_remain_read_only_and_revocation_refuses_them(): void
    {
        $this->seedStock($this->item, $this->location, 2);
        $this->event->lock();
        foreach (['index', 'data', 'options', 'export'] as $action) {
            $this->get(route('check-in.history.'.$action))->assertOk();
        }
        $this->getJson(route('credentials.entitlements.history', $this->item))->assertOk();
        $this->getJson(route('credentials.entitlements.holdings', $this->item))->assertOk();
        $this->assertDatabaseCount('stock_movements', 1);
        $this->event->teamEngagements()->where('person_id', $this->manager->person_id)->first()->role->update(['permissions' => []]);
        foreach (['index', 'data', 'options', 'export'] as $action) {
            $this->get(route('check-in.history.'.$action))->assertForbidden();
        }
    }

    public function test_low_stock_level_preserves_null_zero_omission_and_cache_invalidation(): void
    {
        $this->grantRoleAccess($this->manager, ['credentials.entitlements.edit']);
        $service = app(EntitlementItemService::class);
        $this->assertNull($service->list($this->event)->sole()->low_stock_level);
        foreach ([5, 0, 2147483647, ''] as $level) {
            $this->put(route('credentials.entitlements.update', [$this->event, $this->item]), ['name' => $this->item->name, 'low_stock_level' => $level])->assertSessionHasNoErrors();
            $expected = $level === '' ? null : $level;
            $this->assertSame($expected, $this->item->fresh()->low_stock_level);
            $this->assertSame($expected, $service->list($this->event)->sole()->low_stock_level);
            $this->get(route('credentials.entitlements.show', $this->item))->assertInertia(fn (Assert $p) => $p->where('item.low_stock_level', $expected));
        }
        $this->item->update(['low_stock_level' => 3]);
        $this->put(route('credentials.entitlements.update', [$this->event, $this->item]), ['name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->item->fresh()->low_stock_level);
        $this->post(route('credentials.entitlements.store', $this->event), ['name' => 'New', 'low_stock_level' => 9])->assertSessionHasNoErrors();
        $this->assertSame(9, EntitlementItem::where('name', 'New')->sole()->low_stock_level);
    }

    public function test_low_stock_level_validates_permission_event_lock_and_values_without_changing_stock(): void
    {
        $this->seedStock($this->item, $this->location, 2);
        $payload = ['name' => $this->item->name, 'low_stock_level' => 2];
        $this->put(route('credentials.entitlements.update', [$this->event, $this->item]), $payload)->assertForbidden();
        $this->grantRoleAccess($this->manager, ['credentials.entitlements.edit']);
        foreach ([-1, 1.5, 'text', 2147483648, [], '1e2'] as $level) {
            $this->putJson(route('credentials.entitlements.update', [$this->event, $this->item]), [...$payload, 'low_stock_level' => $level])->assertUnprocessable()->assertJsonValidationErrors('low_stock_level');
        }
        $foreign = Event::create(['name' => 'Other', 'starts_on' => '2027-01-01', 'ends_on' => '2027-01-02', 'timezone' => 'UTC']);
        $item = $foreign->entitlementItems()->create(['name' => 'Other']);
        $this->put(route('credentials.entitlements.update', [$this->event, $item]), $payload)->assertNotFound();
        $this->event->lock();
        $this->putJson(route('credentials.entitlements.update', [$this->event, $this->item]), $payload)->assertForbidden();
        $this->assertNull($this->item->fresh()->low_stock_level);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_create_low_stock_level_validates_whole_units_and_preserves_blank_and_zero(): void
    {
        $this->grantRoleAccess($this->manager, ['credentials.entitlements.edit']);
        foreach ([-1, 2.5, 'text', 2147483648] as $level) {
            $this->postJson(route('credentials.entitlements.store', $this->event), ['name' => 'Invalid', 'low_stock_level' => $level])
                ->assertUnprocessable()->assertJsonValidationErrors('low_stock_level');
        }
        $this->assertDatabaseMissing('entitlement_items', ['name' => 'Invalid']);
        foreach (['' => null, '0' => 0, '2147483647' => 2147483647] as $input => $expected) {
            $name = trim('New level '.$input);
            $this->post(route('credentials.entitlements.store', $this->event), ['name' => $name, 'low_stock_level' => $input])->assertSessionHasNoErrors();
            $this->assertSame($expected, EntitlementItem::where('name', $name)->sole()->low_stock_level);
        }
        $this->event->lock();
        $this->postJson(route('credentials.entitlements.store', $this->event), ['name' => 'Locked', 'low_stock_level' => 5])->assertForbidden();
        $this->assertDatabaseMissing('entitlement_items', ['name' => 'Locked']);
    }

    public function test_low_stock_migration_preserves_existing_data_and_rollback_only_removes_new_setting(): void
    {
        $migration = require database_path('migrations/2026_10_21_000001_add_low_stock_level_to_entitlement_items.php');
        $this->seedStock($this->item, $this->location, 3);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('entitlement_items', 'low_stock_level'));
        $migration->up();
        $this->assertNull($this->item->fresh()->low_stock_level);
        $this->assertSame('Wristband', $this->item->fresh()->name);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(3, app(EntitlementItemService::class)->balance($this->item));
    }

    private function giveOut(StockHolder $source, ?string $code = null): array
    {
        $person = Person::create(['name' => 'Checked-in person', 'email' => 'checked-in@example.test']);
        $pass = $this->event->passTypes()->create(['name' => 'Guest']);
        $patron = $this->event->patrons()->create(['person_id' => $person->id]);
        $assignment = $pass->assignments()->create(['person_id' => $person->id, 'event_patron_id' => $patron->id]);
        $expected = $assignment->expectedEntitlements()->create(['entitlement_item_id' => $this->item->id, 'status' => 'consumed']);
        $issued = $expected->issuedEntitlement()->create(['entitlement_item_id' => $this->item->id, 'code' => $code, 'issued_by' => $this->manager->id, 'issued_at' => now()]);
        $movement = app(StockMovementService::class)->move($this->item, MovementType::GiveOut, 1, $source, null, $this->manager, issued: $issued);

        return [$person, $movement];
    }

    private function csv(string $content): array
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
