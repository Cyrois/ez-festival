<?php

namespace Tests\Feature;

use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreshDatabaseMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_the_baseline_can_seed_roll_back_and_rebuild(): void
    {
        // Migrations create schema only; suggested and demo data belong to seeders.
        foreach (['users', 'people', 'organizations', 'artist_types', 'vendor_types', 'roles'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }

        foreach (Schema::getTableListing() as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(63, strlen($index['name']));
            }
        }

        $this->artisan('db:seed')->assertSuccessful();
        $this->artisan('migrate:reset', ['--force' => true])->assertSuccessful();

        foreach (['users', 'people', 'events', 'roles', 'pass_assignments', 'shift_assignments'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertDatabaseCount('migrations', 0);

        // A rebuilt database starts a new command scope in normal CLI use.
        $this->app->forgetInstance(OrganizationContext::class);

        $this->artisan('migrate', ['--seed' => true, '--force' => true])->assertSuccessful();
        $this->artisan('db:seed')->assertSuccessful();

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('vendor_types', 3);
        $this->assertDatabaseCount('pass_assignments', 6);
        $this->assertDatabaseCount('expected_entitlements', 14);
        $this->assertDatabaseCount('entitlement_adjustments', 9);
        $this->assertDatabaseCount('team_engagements', 4);
    }
}
