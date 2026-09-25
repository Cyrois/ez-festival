<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
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

        $this->assertDatabaseHas('organizations', [
            'id' => 1,
            'name' => 'Festival',
            'active_event_id' => 1,
        ]);

        $this->assertNotNull(Organization::query()->firstOrFail()->setup_completed_at);
        $this->assertSame(1, User::query()->firstOrFail()->current_event_id);

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

        $this->assertDatabaseHas('pass_assignments', [
            'artist_engagement_id' => 1,
            'person_id' => Person::query()->where('email', 'maya@example.com')->sole()->id,
        ]);
        $this->assertDatabaseHas('pass_assignments', [
            'vendor_engagement_id' => 1,
            'person_id' => Person::query()->where('email', 'priya@example.com')->sole()->id,
        ]);
    }
}
