<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class MealTypeMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_migration_leaves_existing_events_without_meal_types(): void
    {
        $migration = require database_path('migrations/2026_10_05_000003_create_meal_types_table.php');
        $meals = require database_path('migrations/2026_10_06_000001_create_meals_table.php');
        $shiftMeals = require database_path('migrations/2026_10_07_000001_create_shift_meals_tables.php');
        $claims = require database_path('migrations/2026_10_07_000002_create_meal_claims_table.php');
        $claims->down();
        $shiftMeals->down();
        $meals->down();
        $migration->down();
        $events = [];
        foreach ([false, true] as $locked) {
            $event = Event::create(['name' => 'Existing', 'starts_on' => '2027-01-01', 'ends_on' => '2027-01-02', 'timezone' => 'UTC']);
            $event->forceFill(['locked' => $locked])->save();
            $events[] = $event;
        }
        $migration->up();
        $meals->up();
        $shiftMeals->up();
        $claims->up();
        $this->assertDatabaseCount('meal_types', 0);
        $this->assertFalse($events[0]->fresh()->isLocked());
        $this->assertTrue($events[1]->fresh()->isLocked());
    }
}
