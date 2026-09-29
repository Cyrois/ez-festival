<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_database_seeder_can_be_resolved_and_run(): void
    {
        $this->artisan('db:seed')->assertSuccessful();

        Person::query()
            ->whereIn('name', ['Calvin Kyle Chan', 'Maya Chen', 'Priya Nair', 'Morgan West'])
            ->update(['email' => 'stale@example.test']);

        $this->artisan('db:seed')->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'calvinkylechan@gmail.com',
        ]);

        $organization = Organization::query()->where('name', 'Festival')->sole();
        $event = Event::query()->where('name', 'Sunrise Folk Fest 2026')->sole();
        $user = User::query()->where('email', 'calvinkylechan@gmail.com')->sole();

        $this->assertSame($event->id, $organization->active_event_id);
        $this->assertNotNull($organization->setup_completed_at);
        $this->assertSame($event->id, $user->current_event_id);

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('locations', 3);
        $this->assertDatabaseCount('artist_types', 3);
        $this->assertDatabaseCount('vendor_types', 3);
        $this->assertDatabaseCount('pass_types', 3);
        $this->assertDatabaseCount('entitlement_items', 3);
        $this->assertDatabaseCount('pass_type_entitlements', 7);
        $this->assertDatabaseCount('entitlement_adjustments', 9);
        $this->assertDatabaseCount('artists', 3);
        $this->assertDatabaseCount('artist_engagements', 3);
        $this->assertDatabaseCount('vendors', 3);
        $this->assertDatabaseCount('vendor_engagements', 3);
        $this->assertDatabaseCount('pass_assignments', 6);
        $this->assertDatabaseCount('expected_entitlements', 14);
        $this->assertDatabaseCount('artist_engagement_people', 3);
        $this->assertDatabaseCount('vendor_engagement_people', 3);
        $this->assertSame(0, Person::query()->whereNull('email')->count());

        $artistEngagement = Artist::query()
            ->where('name', 'River Hollow')
            ->sole()
            ->engagements()
            ->whereBelongsTo($event)
            ->sole();
        $vendorEngagement = Vendor::query()
            ->where('name', 'Cedar Craft Co')
            ->sole()
            ->engagements()
            ->whereBelongsTo($event)
            ->sole();

        $this->assertDatabaseHas('pass_assignments', [
            'artist_engagement_id' => $artistEngagement->id,
            'person_id' => Person::query()->where('email', 'maya@example.com')->sole()->id,
        ]);
        $this->assertDatabaseHas('pass_assignments', [
            'vendor_engagement_id' => $vendorEngagement->id,
            'person_id' => Person::query()->where('email', 'priya@example.com')->sole()->id,
        ]);
    }
}
