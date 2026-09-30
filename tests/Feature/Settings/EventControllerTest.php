<?php

namespace Tests\Feature\Settings;

use App\Models\Event;
use App\Models\ExpectedEntitlement;
use App\Models\IssuedEntitlement;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_user_can_open_and_submit_the_create_event_form(): void
    {
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        $organization = app(OrganizationContext::class)->organization();
        $organization->markSetupComplete();

        $this->actingAs($user)
            ->get(route('settings.events.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Events/Create')
                ->has('timezones'));

        $this->post(route('settings.events.store'), [
            'name' => 'Coastal Folk Festival 2027',
            'starts_on' => '2027-07-10',
            'ends_on' => '2027-07-12',
            'timezone' => 'America/Vancouver',
        ])->assertRedirect(route('settings.events.index'));

        $this->assertDatabaseHas(Event::class, [
            'name' => 'Coastal Folk Festival 2027',
            'timezone' => 'America/Vancouver',
        ]);

        $event = Event::query()->sole();
        $this->assertSame('2027-07-10', $event->starts_on->toDateString());
        $this->assertSame('2027-07-12', $event->ends_on->toDateString());
    }

    public function test_create_event_requires_valid_dates(): void
    {
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        app(OrganizationContext::class)->organization()->markSetupComplete();

        $this->actingAs($user)
            ->from(route('settings.events.create'))
            ->post(route('settings.events.store'), [
                'name' => 'Coastal Folk Festival 2027',
                'starts_on' => '2027-07-12',
                'ends_on' => '2027-07-10',
                'timezone' => 'America/Vancouver',
            ])
            ->assertRedirect(route('settings.events.create'))
            ->assertSessionHasErrors('ends_on');

        $this->assertDatabaseCount(Event::class, 0);
    }

    public function test_user_can_delete_an_unlocked_event(): void
    {
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        app(OrganizationContext::class)->organization()->markSetupComplete();
        $event = Event::query()->create([
            'name' => 'Coastal Folk Festival 2027',
            'starts_on' => '2027-07-10',
            'ends_on' => '2027-07-12',
            'timezone' => 'America/Vancouver',
        ]);

        $this->actingAs($user)
            ->delete(route('settings.events.destroy', $event))
            ->assertRedirect(route('settings.events.index'));

        $this->assertModelMissing($event);
    }

    public function test_user_can_delete_an_event_that_has_shifts(): void
    {
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        app(OrganizationContext::class)->organization()->markSetupComplete();
        $event = Event::query()->create([
            'name' => 'Coastal Folk Festival 2027',
            'starts_on' => '2027-07-10',
            'ends_on' => '2027-07-12',
            'timezone' => 'America/Vancouver',
        ]);
        $location = $event->locations()->create(['name' => 'Main stage']);
        $shift = $event->shifts()->create([
            'location_id' => $location->id,
            'name' => 'Show run',
            'starts_at' => '2027-07-10 14:00:00',
            'ends_at' => '2027-07-10 22:00:00',
        ]);

        $this->actingAs($user)
            ->delete(route('settings.events.destroy', $event))
            ->assertRedirect(route('settings.events.index'));

        $this->assertModelMissing($event);
        $this->assertModelMissing($location);
        $this->assertModelMissing($shift);
    }

    public function test_user_can_delete_an_event_with_every_restricted_related_record(): void
    {
        $this->seed();

        $organization = Organization::query()->where('name', 'Festival')->sole();
        $event = $organization->activeEvent()->sole();
        $user = User::query()->where('email', 'calvinkylechan@gmail.com')->sole();
        $location = $event->locations()->firstOrFail();
        $expected = ExpectedEntitlement::query()
            ->whereIn('entitlement_item_id', $event->entitlementItems()->select('id'))
            ->firstOrFail();

        $issued = IssuedEntitlement::query()->create([
            'expected_entitlement_id' => $expected->id,
            'entitlement_item_id' => $expected->entitlement_item_id,
            'location_id' => $location->id,
            'code' => 'EVENT-DELETE-TEST',
            'issued_by' => $user->id,
        ]);
        $shift = $event->shifts()->create([
            'location_id' => $location->id,
            'name' => 'Event teardown',
            'starts_at' => '2026-07-12 20:00:00',
            'ends_at' => '2026-07-12 23:00:00',
        ]);

        $this->actingAs($user)
            ->delete(route('settings.events.destroy', $event))
            ->assertRedirect(route('settings.events.index'));

        $this->assertModelMissing($event);
        $this->assertModelMissing($location);
        $this->assertModelMissing($issued);
        $this->assertModelMissing($shift);
        $this->assertDatabaseCount('pass_assignments', 0);
        $this->assertDatabaseCount('pass_type_entitlements', 0);
        $this->assertDatabaseCount('entitlement_adjustments', 0);
        $this->assertDatabaseCount('expected_entitlements', 0);
        $this->assertDatabaseCount('team_engagements', 0);
    }

    public function test_user_cannot_delete_a_locked_event(): void
    {
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        app(OrganizationContext::class)->organization()->markSetupComplete();
        $event = Event::query()->create([
            'name' => 'Coastal Folk Festival 2027',
            'starts_on' => '2027-07-10',
            'ends_on' => '2027-07-12',
            'timezone' => 'America/Vancouver',
        ]);
        $event->lock();

        $this->actingAs($user)
            ->delete(route('settings.events.destroy', $event))
            ->assertForbidden();

        $this->assertModelExists($event);
    }
}
