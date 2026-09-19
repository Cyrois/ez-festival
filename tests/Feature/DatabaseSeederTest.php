<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_database_seeder_can_be_resolved_and_run(): void
    {
        $this->artisan('db:seed')->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'calvinkylechan@gmail.com',
        ]);

        $this->assertDatabaseHas('organizations', [
            'id' => 1,
            'name' => 'Festival',
        ]);
    }
}
