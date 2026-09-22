<?php

namespace Tests\Feature\Settings;

use App\Models\Event;
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

    public function test_user_can_delete_a_location_without_adjustments(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);

        $this->actingAs($user)
            ->delete(route('settings.events.locations.destroy', [$event, $location]))
            ->assertRedirect(route('settings.events.locations', $event))
            ->assertSessionHas('success', 'Location deleted.');

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_deleting_a_location_with_adjustments_is_blocked(): void
    {
        [$user, $event] = $this->eventContext();
        $location = $event->locations()->create(['name' => 'Main stage']);
        $item = $event->entitlementItems()->create(['name' => 'Artist wristband']);
        $item->adjustments()->create([
            'location_id' => $location->id,
            'delta' => 10,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('settings.events.locations', $event))
            ->delete(route('settings.events.locations.destroy', [$event, $location]))
            ->assertRedirect(route('settings.events.locations', $event))
            ->assertSessionHasErrors('location');

        $this->assertDatabaseHas('locations', ['id' => $location->id]);
        $this->assertDatabaseHas('entitlement_adjustments', [
            'entitlement_item_id' => $item->id,
            'location_id' => $location->id,
        ]);
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

        return [$user, $event];
    }
}
