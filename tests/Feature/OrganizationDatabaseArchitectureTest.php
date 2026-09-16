<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationDatabaseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_schema_keeps_org_membership_without_row_scoped_tenancy(): void
    {
        $this->assertTrue(Schema::hasTable('organizations'));
        $this->assertTrue(Schema::hasTable('organization_user'));
        $this->assertFalse(Schema::hasTable('application_state'));
        $this->assertFalse(Schema::hasTable('organization_artists'));
        $this->assertTrue(Schema::hasTable('artists'));

        foreach (['events', 'vendor_types', 'artist_types', 'artists', 'artist_labels'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'organization_id'));
        }
    }

    public function test_users_inherit_the_organization_default_and_can_select_their_own_event(): void
    {
        $default = $this->event('Default festival');
        $selected = $this->event('Selected festival');
        $first = User::factory()->create();
        $second = User::factory()->create();
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($default);

        $this->assertTrue($first->effectiveEvent()->is($default));
        $this->assertTrue($second->effectiveEvent()->is($default));

        $first->setCurrentEvent($selected);

        $this->assertTrue($first->fresh()->effectiveEvent()->is($selected));
        $this->assertTrue($second->fresh()->effectiveEvent()->is($default));
        $this->assertDatabaseCount('organizations', 1);
        $this->assertSame($default->id, Organization::query()->sole()->active_event_id);
    }

    public function test_deleted_user_event_falls_back_to_the_organization_default(): void
    {
        $default = $this->event('Default festival');
        $selected = $this->event('Selected festival');
        $user = User::factory()->create();
        app(OrganizationContext::class)->setDefaultEvent($default);
        $user->setCurrentEvent($selected);

        $selected->delete();

        $user->refresh();
        $this->assertNull($user->current_event_id);
        $this->assertTrue($user->effectiveEvent()->is($default));
    }

    public function test_setup_uses_configured_organization_name_and_persists_organization_wide_state(): void
    {
        $user = User::factory()->create();
        app(OrganizationContext::class)->organization()->update(['name' => 'Coastal Folk Festival']);

        $this->actingAs($user)->get(route('setup.event'))->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Event')
            ->where('organization.name', 'Coastal Folk Festival')
            ->where('event', null));

        $this->assertTrue($user->organizations()->exists());

        $this->post(route('setup.event'), [
            'name' => 'Coastal Folk Festival 2027',
            'starts_on' => '2027-07-10',
            'ends_on' => '2027-07-12',
            'timezone' => 'America/Vancouver',
        ])->assertRedirect(route('setup.locations'));

        $event = Event::query()->sole();
        $this->assertTrue(app(OrganizationContext::class)->defaultEvent()->is($event));
        $this->assertSame($event->id, $user->fresh()->current_event_id);

        $this->post(route('setup.ready.complete'))->assertRedirect(route('dashboard'));
        $this->assertTrue(app(OrganizationContext::class)->setupIsComplete());
        $this->assertNotNull(Organization::query()->sole()->setup_completed_at);

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->get(route('dashboard'))->assertOk();
        $this->assertTrue($otherUser->organizations()->exists());
    }

    private function event(string $name): Event
    {
        return Event::query()->create([
            'name' => $name,
            'starts_on' => '2027-06-01',
            'ends_on' => '2027-06-03',
            'timezone' => 'America/Vancouver',
        ]);
    }
}
