<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventStockItem;
use App\Models\EventStockItemMovement;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventStockItemsTest extends TestCase
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

    public function test_index_returns_ordered_items(): void
    {
        [$user, $event] = $this->createEventContext();
        $meal = $event->stockItems()->create(['name' => 'Meal voucher', 'balance' => 500]);
        $artist = $event->stockItems()->create(['name' => 'Artist wristband', 'balance' => 240]);

        $this->actingAs($user)->get(route('credentials.entitlements'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Credentials/Entitlements')
                ->where('items.0.name', 'Artist wristband')
                ->where('items.1.name', 'Meal voucher')
                ->where('canWrite', true),
        );
    }

    public function test_user_can_create_an_item_with_opening_movement(): void
    {
        [$user, $event] = $this->createEventContext();

        $this->actingAs($user)->post(route('credentials.entitlements.store', $event), [
            'name' => ' Artist wristband ',
            'opening_balance' => 240,
        ])->assertRedirect(route('credentials.entitlements'));

        $item = EventStockItem::query()->sole();

        $this->assertSame('Artist wristband', $item->name);
        $this->assertSame(240, $item->balance);
        $this->assertDatabaseHas('event_stock_item_movements', [
            'event_stock_item_id' => $item->id,
            'kind' => EventStockItemMovement::KIND_OPENING,
            'quantity_delta' => 240,
            'balance_after' => 240,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_name_is_unique_per_event_ignoring_case(): void
    {
        [$user, $event] = $this->createEventContext();
        $event->stockItems()->create(['name' => 'Artist wristband', 'balance' => 0]);

        $this->actingAs($user)->post(route('credentials.entitlements.store', $event), [
            'name' => ' ARTIST WRISTBAND ',
            'opening_balance' => 1,
        ])->assertSessionHasErrors('name');

        $otherEvent = Event::query()->create([
            'name' => 'Other Festival',
            'starts_on' => '2027-07-10',
            'ends_on' => '2027-07-12',
            'timezone' => 'America/Vancouver',
        ]);
        $otherEvent->stockItems()->create(['name' => 'Artist wristband', 'balance' => 0]);

        $this->assertSame(2, EventStockItem::query()->count());
    }

    public function test_rename_does_not_write_a_movement(): void
    {
        [$user, $event] = $this->createEventContext();
        $item = $event->stockItems()->create(['name' => 'Guest wristband', 'balance' => 5]);

        $this->actingAs($user)->put(route('credentials.entitlements.update', [$event, $item]), [
            'name' => 'Guest band',
        ])->assertRedirect(route('credentials.entitlements'));

        $this->assertDatabaseHas('event_stock_items', ['id' => $item->id, 'name' => 'Guest band']);
        $this->assertDatabaseCount('event_stock_item_movements', 0);
    }

    public function test_adjustments_update_balance_and_write_a_signed_movement(): void
    {
        [$user, $event] = $this->createEventContext();
        $item = $event->stockItems()->create(['name' => 'Meal voucher', 'balance' => 10]);

        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'direction' => 'remove',
            'quantity' => 3,
            'reason' => 'Damaged vouchers',
        ])->assertRedirect(route('credentials.entitlements'));

        $this->assertDatabaseHas('event_stock_items', ['id' => $item->id, 'balance' => 7]);
        $this->assertDatabaseHas('event_stock_item_movements', [
            'event_stock_item_id' => $item->id,
            'kind' => EventStockItemMovement::KIND_ADJUSTMENT,
            'quantity_delta' => -3,
            'balance_after' => 7,
            'reason' => 'Damaged vouchers',
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_adjustment_cannot_reduce_balance_below_zero(): void
    {
        [$user, $event] = $this->createEventContext();
        $item = $event->stockItems()->create(['name' => 'Guest wristband', 'balance' => 1]);

        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'direction' => 'remove',
            'quantity' => 2,
            'reason' => 'Correction',
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('event_stock_items', ['id' => $item->id, 'balance' => 1]);
        $this->assertDatabaseCount('event_stock_item_movements', 0);
    }

    public function test_item_from_another_event_cannot_be_mutated(): void
    {
        [$user, $event] = $this->createEventContext();
        $otherEvent = Event::query()->create([
            'name' => 'Other Festival',
            'starts_on' => '2027-07-10',
            'ends_on' => '2027-07-12',
            'timezone' => 'America/Vancouver',
        ]);
        $item = $otherEvent->stockItems()->create(['name' => 'Guest wristband', 'balance' => 2]);

        $this->actingAs($user)->put(route('credentials.entitlements.update', [$event, $item]), [
            'name' => 'Changed',
        ])->assertNotFound();
    }

    public function test_locked_event_is_read_only(): void
    {
        [$user, $event] = $this->createEventContext();
        $item = $event->stockItems()->create(['name' => 'Guest wristband', 'balance' => 2]);
        $event->lock();

        $this->actingAs($user)->get(route('credentials.entitlements'))->assertInertia(
            fn (Assert $page) => $page->where('canWrite', false),
        );
        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'direction' => 'remove',
            'quantity' => 1,
            'reason' => 'Correction',
        ])->assertForbidden();
    }

    /**
     * @return array{User, Event}
     */
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
