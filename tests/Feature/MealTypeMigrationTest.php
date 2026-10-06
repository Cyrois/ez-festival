<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MealTypeMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_backfill_gives_open_and_locked_existing_events_four_types(): void
    {
        $migration = require database_path('migrations/2026_10_05_000003_create_meal_types_table.php');
        $migration->down();
        $events = [];
        foreach ([false, true] as $locked) {
            $event = Event::create(['name' => 'Existing', 'starts_on' => '2027-01-01', 'ends_on' => '2027-01-02', 'timezone' => 'UTC']);
            $event->forceFill(['locked' => $locked])->save();
            $events[] = $event;
        }
        $migration->up();
        foreach ($events as $event) {
            $rows = DB::table('meal_types')->where('event_id', $event->id)->orderBy('sort_order')->get();
            $this->assertSame(['Breakfast', 'Lunch', 'Dinner', 'Midnight'], $rows->pluck('name')->all());
            $this->assertSame(['07:00:00', '11:00:00', '17:00:00', '23:00:00'], $rows->pluck('starts_at')->all());
            $this->assertSame(['10:00:00', '14:00:00', '20:00:00', '01:00:00'], $rows->pluck('ends_at')->all());
        }
        $this->assertTrue($events[1]->fresh()->isLocked());
    }
}
