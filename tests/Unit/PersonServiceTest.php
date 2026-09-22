<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Services\PersonService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PersonServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_find_or_create_by_email_updates_name_and_phone_for_existing_person(): void
    {
        $person = Person::query()->create([
            'name' => 'Old Name',
            'email' => 'alex@example.com',
            'phone' => '111',
        ]);

        $result = app(PersonService::class)->findOrCreateByEmail([
            'name' => 'New Name',
            'email' => '  Alex@Example.com ',
            'phone' => '222',
        ]);

        $this->assertTrue($result->is($person));
        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'name' => 'New Name',
            'email' => 'alex@example.com',
            'phone' => '222',
        ]);
    }

    public function test_find_or_create_by_email_creates_when_missing(): void
    {
        $result = app(PersonService::class)->findOrCreateByEmail([
            'name' => 'Fresh Person',
            'email' => 'fresh@example.com',
            'phone' => '333',
        ]);

        $this->assertDatabaseHas('people', [
            'id' => $result->id,
            'name' => 'Fresh Person',
            'email' => 'fresh@example.com',
            'phone' => '333',
        ]);
    }
}
