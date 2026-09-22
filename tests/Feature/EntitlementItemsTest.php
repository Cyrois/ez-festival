<?php

namespace Tests\Feature;

use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EntitlementItemsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_must_sign_in(): void
    {
        $this->get(route('credentials.entitlements'))->assertRedirect(route('login'));
    }

    public function test_index_exposes_an_adjustment_sum_as_the_balance(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $item = $event->entitlementItems()->create(['name' => 'Meal voucher']);
        $item->adjustments()->createMany([
            ['location_id' => $location->id, 'delta' => 500],
            ['location_id' => $location->id, 'delta' => -20],
            ['location_id' => null, 'delta' => 5],
        ]);

        $this->actingAs($user)->get(route('credentials.entitlements'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Credentials/Entitlements')
                ->where('items.0.name', 'Meal voucher')
                ->where('items.0.balance', 485),
        );
    }

    public function test_edit_page_exposes_live_quantities_locations_usage_and_issued_log(): void
    {
        [$user, $event] = $this->createEventContext();
        $headquarters = $event->locations()->create(['name' => 'Headquarters']);
        $mainStage = $event->locations()->create(['name' => 'Main stage']);
        $event->locations()->create(['name' => 'Zero stock']);
        $label = $event->entitlementItemLabels()->create(['name' => 'Artist', 'color' => 'primary']);
        $item = $event->entitlementItems()->create(['name' => 'Meal voucher']);
        $item->labels()->attach($label);
        $item->adjustments()->createMany([
            ['location_id' => $headquarters->id, 'delta' => 40],
            ['location_id' => $mainStage->id, 'delta' => 60],
        ]);
        $passType = $event->passTypes()->create(['name' => 'Artist']);
        $passType->entitlements()->createMany([
            ['entitlement_item_id' => $item->id, 'sort_order' => 0],
            ['entitlement_item_id' => $item->id, 'sort_order' => 1],
        ]);
        $patron = $event->patrons()->create(['person_id' => $user->person_id]);
        $assignment = $passType->assignments()->create(['event_patron_id' => $patron->id]);
        $assignment->expectedEntitlements()->create([
            'entitlement_item_id' => $item->id,
            'status' => ExpectedEntitlement::STATUS_EXPECTED,
        ]);
        $consumed = $assignment->expectedEntitlements()->create([
            'entitlement_item_id' => $item->id,
            'status' => ExpectedEntitlement::STATUS_CONSUMED,
        ]);
        $consumed->issuedEntitlement()->create([
            'entitlement_item_id' => $item->id,
            'code' => 'WH-4812',
            'issued_by' => $user->id,
            'issued_at' => now(),
        ]);

        $this->actingAs($user)->get(route('credentials.entitlements.edit', $item))->assertInertia(
            fn (Assert $page) => $page
                ->component('Credentials/EditEntitlement')
                ->where('event.id', $event->id)
                ->where('item.name', 'Meal voucher')
                ->where('item.labels.0.id', $label->id)
                ->where('labels.0.id', $label->id)
                ->where('stats.in_stock', 100)
                ->where('stats.expected', 1)
                ->where('stats.issued', 1)
                ->where('locations.0.name', 'Headquarters')
                ->where('locations.0.in_stock', 40)
                ->where('locations.2.name', 'Zero stock')
                ->where('locations.2.in_stock', 0)
                ->where('pass_usage.0.pass_type_name', 'Artist')
                ->where('pass_usage.0.line_count', 2)
                ->where('issued_log.0.pass_name', 'Artist')
                ->where('issued_log.0.code', 'WH-4812')
                ->where('issued_log.0.issued_by.name', $user->name)
                ->where('is_read_only', false),
        );
    }

    public function test_create_writes_an_opening_adjustment_and_uses_event_item_labels(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $label = $event->entitlementItemLabels()->create(['name' => 'Artist', 'color' => 'primary']);

        $this->actingAs($user)->post(route('credentials.entitlements.store', $event), [
            'name' => ' Artist wristband ',
            'opening_balance' => 240,
            'location_id' => $location->id,
            'label_ids' => [$label->id],
        ])->assertRedirect(route('credentials.entitlements'));

        $item = EntitlementItem::query()->sole();
        $this->assertSame('Artist wristband', $item->name);
        $this->assertSame(240, $item->adjustments()->sum('delta'));
        $this->assertDatabaseHas('entitlement_adjustments', [
            'entitlement_item_id' => $item->id,
            'location_id' => $location->id,
            'delta' => 240,
            'user_id' => $user->id,
        ]);
        $this->assertTrue($item->labels()->whereKey($label)->exists());
    }

    public function test_adjustments_are_ledger_rows_and_cannot_overdraw(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $item = $event->entitlementItems()->create(['name' => 'Guest wristband']);
        $item->adjustments()->create(['location_id' => $location->id, 'delta' => 3]);

        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'location_id' => $location->id,
            'direction' => 'remove',
            'quantity' => 2,
            'reason' => 'Damaged',
        ])->assertRedirect(route('credentials.entitlements.edit', $item));

        $this->assertSame(1, $item->adjustments()->sum('delta'));
        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'location_id' => $location->id,
            'direction' => 'remove',
            'quantity' => 2,
        ])->assertSessionHasErrors('quantity');
    }

    public function test_adjustment_without_a_location_creates_unassigned_inventory(): void
    {
        [$user, $event] = $this->createEventContext();
        $item = $event->entitlementItems()->create(['name' => 'Guest wristband']);

        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'location_id' => '',
            'direction' => 'add',
            'quantity' => 10,
            'reason' => 'Initial stock',
        ])->assertRedirect(route('credentials.entitlements.edit', $item));

        $this->assertDatabaseHas('entitlement_adjustments', [
            'entitlement_item_id' => $item->id,
            'location_id' => null,
            'delta' => 10,
            'reason' => 'Initial stock',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)->get(route('credentials.entitlements.edit', $item))->assertInertia(
            fn (Assert $page) => $page
                ->where('stats.in_stock', 10)
                ->where('locations.0.id', null)
                ->where('locations.0.name', 'Unassigned')
                ->where('locations.0.in_stock', 10),
        );
    }

    public function test_unassigned_inventory_cannot_be_overdrawn(): void
    {
        [$user, $event] = $this->createEventContext();
        $item = $event->entitlementItems()->create(['name' => 'Guest wristband']);
        $item->adjustments()->create(['location_id' => null, 'delta' => 3]);

        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'direction' => 'remove',
            'quantity' => 4,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(3, $item->adjustments()->sum('delta'));
    }

    public function test_adjustment_cannot_use_stock_from_another_location(): void
    {
        [$user, $event] = $this->createEventContext();
        $stocked = $event->locations()->create(['name' => 'Main stage']);
        $empty = $event->locations()->create(['name' => 'Headquarters']);
        $item = $event->entitlementItems()->create(['name' => 'Guest wristband']);
        $item->adjustments()->create(['location_id' => $stocked->id, 'delta' => 10]);

        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'location_id' => $empty->id,
            'direction' => 'remove',
            'quantity' => 1,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(10, $item->adjustments()->sum('delta'));
    }

    public function test_adjustment_rejects_a_location_from_another_event(): void
    {
        [$user, $event] = $this->createEventContext();
        $otherEvent = Event::query()->create([
            'name' => 'Other festival',
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-12',
            'timezone' => 'America/Vancouver',
        ]);
        $foreignLocation = $otherEvent->locations()->create(['name' => 'Other stage']);
        $item = $event->entitlementItems()->create(['name' => 'Guest wristband']);

        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'location_id' => $foreignLocation->id,
            'direction' => 'add',
            'quantity' => 1,
        ])->assertSessionHasErrors('location_id');

        $this->assertDatabaseCount('entitlement_adjustments', 0);
    }

    public function test_location_deep_link_prefills_and_opens_adjustment(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $item = $event->entitlementItems()->create(['name' => 'Guest wristband']);

        $this->actingAs($user)
            ->get(route('credentials.entitlements.edit', $item).'?loc='.$location->id)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('adjust_location_id', $location->id)
                    ->where('open_adjust', true),
            );
    }

    public function test_update_changes_details_without_touching_inventory(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $label = $event->entitlementItemLabels()->create(['name' => 'Artist', 'color' => 'primary']);
        $item = $event->entitlementItems()->create(['name' => 'Guest wristband']);
        $item->adjustments()->create(['location_id' => $location->id, 'delta' => 10]);

        $this->actingAs($user)->put(route('credentials.entitlements.update', [$event, $item]), [
            'name' => 'Artist wristband',
            'label_ids' => [$label->id],
        ])->assertRedirect(route('credentials.entitlements'));

        $this->assertSame('Artist wristband', $item->fresh()->name);
        $this->assertTrue($item->labels()->whereKey($label)->exists());
        $this->assertSame(10, $item->adjustments()->sum('delta'));
    }

    public function test_locked_event_edit_page_is_read_only(): void
    {
        [$user, $event] = $this->createEventContext();
        $item = $event->entitlementItems()->create(['name' => 'Guest wristband']);
        $event->lock();

        $this->actingAs($user)->get(route('credentials.entitlements.edit', $item))->assertInertia(
            fn (Assert $page) => $page
                ->component('Credentials/EditEntitlement')
                ->where('is_read_only', true),
        );
    }

    public function test_locked_event_rejects_mutations(): void
    {
        [$user, $event] = $this->createEventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $item = $event->entitlementItems()->create(['name' => 'Guest wristband']);
        $event->lock();

        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'location_id' => $location->id,
            'direction' => 'add',
            'quantity' => 1,
        ])->assertForbidden();
    }

    public function test_destroy_without_manage_credentials_is_unauthorized(): void
    {
        [$user, $event] = $this->createEventContext();
        $item = $event->entitlementItems()->create(['name' => 'Guest wristband']);

        Gate::define('manage-credentials', fn (): bool => false);

        $this->actingAs($user)
            ->delete(route('credentials.entitlements.destroy', [$event, $item]))
            ->assertForbidden();

        $this->assertDatabaseHas('entitlement_items', ['id' => $item->id]);
    }

    /** @return array{User, Event} */
    private function createEventContext(): array
    {
        $user = User::factory()->create();
        $event = Event::query()->create([
            'name' => 'Sunrise Folk Fest 2026',
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-12',
            'timezone' => 'America/Vancouver',
        ]);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        return [$user, $event];
    }
}
