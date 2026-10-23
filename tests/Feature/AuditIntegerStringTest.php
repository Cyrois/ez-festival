<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditIntegerStringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_numeric_string_quantities_obey_postgresql_limits(): void
    {
        [$user, $event] = $this->context();
        $location = $event->locations()->create(['name' => 'Gate']);
        $this->actingAs($user)->postJson(route('credentials.passes.store', $event), ['name' => 'Too Large', 'max_assignments' => '2147483648'])
            ->assertUnprocessable()->assertJsonValidationErrors('max_assignments');
        $this->postJson(route('credentials.entitlements.store', $event), ['name' => 'Too Large', 'opening_balance' => '2147483648', 'location_id' => $location->id])
            ->assertUnprocessable()->assertJsonValidationErrors('opening_balance');
        $item = $event->entitlementItems()->create(['name' => 'Stock']);
        foreach (['add', 'remove'] as $direction) {
            $this->postJson(route('credentials.entitlements.adjustments.store', [$event, $item]), ['location_id' => $location->id, 'direction' => $direction, 'quantity' => '2147483648'])
                ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        }
        $this->assertDatabaseMissing('pass_types', ['name' => 'Too Large']);
        $this->assertDatabaseMissing('entitlement_items', ['name' => 'Too Large']);
        $this->assertSame(0, $item->adjustments()->count());
    }

    private function context(): array
    {
        $event = Event::create(['name' => 'Database Regression', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        $user->setCurrentEvent($event);

        return [$user, $event];
    }
}
