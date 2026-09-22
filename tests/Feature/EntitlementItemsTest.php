<?php

namespace Tests\Feature;

use App\Models\EntitlementItem;
use App\Models\Event;
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
        $item = $event->entitlementItems()->create(['name' => 'Meal voucher']);
        $item->adjustments()->createMany([['delta' => 500], ['delta' => -20]]);

        $this->actingAs($user)->get(route('credentials.entitlements'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Credentials/Entitlements')
                ->where('items.0.name', 'Meal voucher')
                ->where('items.0.balance', 480),
        );
    }

    public function test_create_writes_an_opening_adjustment_and_uses_event_item_labels(): void
    {
        [$user, $event] = $this->createEventContext();
        $label = $event->entitlementItemLabels()->create(['name' => 'Artist', 'color' => 'primary']);

        $this->actingAs($user)->post(route('credentials.entitlements.store', $event), [
            'name' => ' Artist wristband ',
            'opening_balance' => 240,
            'label_ids' => [$label->id],
        ])->assertRedirect(route('credentials.entitlements'));

        $item = EntitlementItem::query()->sole();
        $this->assertSame('Artist wristband', $item->name);
        $this->assertSame(240, $item->adjustments()->sum('delta'));
        $this->assertDatabaseHas('entitlement_adjustments', [
            'entitlement_item_id' => $item->id,
            'delta' => 240,
            'user_id' => $user->id,
        ]);
        $this->assertTrue($item->labels()->whereKey($label)->exists());
    }

    public function test_adjustments_are_ledger_rows_and_cannot_overdraw(): void
    {
        [$user, $event] = $this->createEventContext();
        $item = $event->entitlementItems()->create(['name' => 'Guest wristband']);
        $item->adjustments()->create(['delta' => 3]);

        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'direction' => 'remove',
            'quantity' => 2,
            'reason' => 'Damaged',
        ])->assertRedirect(route('credentials.entitlements'));

        $this->assertSame(1, $item->adjustments()->sum('delta'));
        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
            'direction' => 'remove',
            'quantity' => 2,
        ])->assertSessionHasErrors('quantity');
    }

    public function test_locked_event_rejects_mutations(): void
    {
        [$user, $event] = $this->createEventContext();
        $item = $event->entitlementItems()->create(['name' => 'Guest wristband']);
        $event->lock();

        $this->actingAs($user)->post(route('credentials.entitlements.adjustments.store', [$event, $item]), [
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
