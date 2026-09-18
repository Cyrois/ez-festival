<?php

namespace Tests\Feature\Settings;

use App\Models\Event;
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

    public function test_user_cannot_delete_a_locked_event(): void
    {
        $user = User::factory()->create();
        app(OrganizationContext::class)->organization()->markSetupComplete();
        $event = Event::query()->create([
            'name' => 'Coastal Folk Festival 2027',
            'starts_on' => '2027-07-10',
            'ends_on' => '2027-07-12',
            'timezone' => 'America/Vancouver',
            'locked' => true,
        ]);

        $this->actingAs($user)
            ->delete(route('settings.events.destroy', $event))
            ->assertForbidden();

        $this->assertModelExists($event);
    }
}
