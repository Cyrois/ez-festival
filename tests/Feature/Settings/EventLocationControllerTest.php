<?php

namespace Tests\Feature\Settings;

use App\Models\Event;
use App\Models\StockHolder;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventLocationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_user_can_delete_a_location_without_stock_history(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);

        $this->actingAs($user)
            ->delete(route('settings.events.locations.destroy', [$event, $location]))
            ->assertRedirect(route('settings.events.locations', $event))
            ->assertSessionHas('success', 'Location deleted.');

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_deleting_a_location_with_stock_history_is_blocked(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $item = $event->entitlementItems()->create(['name' => 'Artist wristband']);
        $this->seedStock($item, $location, 10, $user);

        $this->actingAs($user)
            ->from(route('settings.events.locations', $event))
            ->delete(route('settings.events.locations.destroy', [$event, $location]))
            ->assertRedirect(route('settings.events.locations', $event))
            ->assertSessionHasErrors('location');

        $this->assertDatabaseHas('locations', ['id' => $location->id]);
        $this->assertDatabaseHas('stock_movements', [
            'entitlement_item_id' => $item->id,
            'to_holder_id' => StockHolder::query()->where('event_id', $item->event_id)->where('location_id', $location->id)->value('id'),
        ]);
    }

    public function test_deleting_a_location_with_shifts_is_blocked(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $shift = $event->shifts()->create([
            'name' => 'Show run',
            'location_id' => $location->id,
            'starts_at' => '2026-07-10 14:00:00',
            'ends_at' => '2026-07-10 22:00:00',
        ]);

        $this->actingAs($user)
            ->from(route('settings.events.locations', $event))
            ->delete(route('settings.events.locations.destroy', [$event, $location]))
            ->assertRedirect(route('settings.events.locations', $event))
            ->assertSessionHasErrors('location');

        $this->assertDatabaseHas('locations', ['id' => $location->id]);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    /** @return array{User, Event} */
    private function eventContext(): array
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
        $this->grantAdminAccess($user);

        return [$user, $event];
    }
}
