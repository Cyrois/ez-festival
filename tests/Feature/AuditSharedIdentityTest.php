<?php

namespace Tests\Feature;

use App\Models\ArtistEngagement;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEngagement;
use App\Services\PersonService;
use App\Support\OrganizationContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditSharedIdentityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_contact_creation_reuses_login_profile_without_overwriting_it(): void
    {
        [$actor,$event] = $this->context();
        $shared = User::factory()->create(['name' => 'Canonical', 'email' => 'canonical@example.test', 'phone' => '111']);
        $owner = ArtistEngagement::factory()->for($event)->create();
        $this->actingAs($actor)->post(route('artists.people.store', $owner), ['name' => 'Overwrite', 'email' => 'CANONICAL@example.test', 'phone' => '222'])->assertSessionHasNoErrors();
        $this->assertSame([$shared->person_id], $owner->people()->pluck('people.id')->all());
        $this->assertSame('Canonical', $shared->person->fresh()->name);
        $this->assertSame('111', $shared->person->fresh()->phone);
    }

    public function test_shared_contact_edit_is_blocked_without_changing_either_domain(): void
    {
        [$actor,$event] = $this->context();
        $person = Person::create(['name' => 'Canonical', 'email' => 'shared@example.test', 'phone' => '111']);
        $artist = ArtistEngagement::factory()->for($event)->create();
        $vendor = VendorEngagement::create(['event_id' => $event->id, 'vendor_id' => Vendor::create(['name' => 'Vendor'])->id, 'status' => 'idea']);
        $artist->people()->attach($person);
        $vendor->people()->attach($person);
        $this->actingAs($actor)->put(route('artists.people.update', [$artist, $person]), ['name' => 'Overwrite', 'email' => $person->email, 'phone' => '222'])->assertSessionHasErrors('email');
        $this->put(route('vendors.update', $vendor), ['name' => 'Vendor', 'status' => 'idea', 'people' => [['id' => $person->id, 'name' => 'Overwrite', 'email' => $person->email, 'phone' => '222']]])->assertSessionHasErrors('email');
        $this->assertSame('Canonical', $person->fresh()->name);
        $this->assertSame('111', $person->fresh()->phone);
        $this->assertSame(1, $artist->people()->count());
        $this->assertSame(1, $vendor->people()->count());
    }

    public function test_normalized_email_uniqueness_has_a_database_backstop(): void
    {
        Person::create(['name' => 'Original', 'email' => 'alex@example.test']);
        try {
            DB::transaction(fn () => Person::create(['name' => 'Duplicate', 'email' => ' ALEX@example.test ']));
            $this->fail('Normalized duplicate emails must be rejected.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, Person::count());
        }
        $reused = app(PersonService::class)->findOrCreateByEmail(['name' => 'Overwrite', 'email' => ' Alex@example.test ']);
        $this->assertSame('Original', $reused->name);
    }

    public function test_migration_refuses_legacy_duplicates_without_merging(): void
    {
        $migration = require database_path('migrations/2026_10_04_000001_enforce_unique_person_email.php');
        $migration->down();
        $first = Person::create(['name' => 'First', 'email' => 'legacy@example.test']);
        $second = Person::create(['name' => 'Second', 'email' => ' LEGACY@example.test ']);
        try {
            $migration->up();
            $this->fail('Legacy duplicates need an explicit resolution.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Resolve duplicate', $exception->getMessage());
            $this->assertSame('First', $first->fresh()->name);
            $this->assertSame('Second', $second->fresh()->name);
        } finally {
            $second->delete();
            $migration->up();
        }
    }

    private function context(): array
    {
        $this->withoutVite();
        $event = Event::create(['name' => 'Festival', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        app(OrganizationContext::class)->setDefaultEvent($event);
        app(OrganizationContext::class)->markSetupComplete();
        $actor = User::factory()->create();
        $this->grantAdminAccess($actor);
        $actor->setCurrentEvent($event);

        return [$actor, $event];
    }
}
