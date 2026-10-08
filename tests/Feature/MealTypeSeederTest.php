<?php

namespace Tests\Feature;

use App\Models\Event;
use Database\Seeders\MealTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealTypeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_initializes_empty_events_and_preserves_configured_events_on_rerun(): void
    {
        $events = collect([false, true])->map(function (bool $locked): Event {
            $event = Event::create(['name' => 'Seeded event', 'starts_on' => '2027-01-01', 'ends_on' => '2027-01-02', 'timezone' => 'UTC']);
            $event->forceFill(['locked' => $locked])->save();

            return $event;
        });
        $this->seed(MealTypeSeeder::class);
        foreach ($events as $event) {
            $types = $event->mealTypes()->orderBy('sort_order')->get();
            $this->assertSame(['Breakfast', 'Lunch', 'Dinner', 'Midnight'], $types->pluck('name')->all());
            $this->assertSame(['07:00:00', '11:00:00', '17:00:00', '23:00:00'], $types->pluck('starts_at')->all());
            $this->assertSame(['10:00:00', '14:00:00', '20:00:00', '01:00:00'], $types->pluck('ends_at')->all());
        }
        $type = $events[0]->mealTypes()->orderBy('sort_order')->first();
        $type->update(['name' => 'Early breakfast', 'starts_at' => '06:00:00']);
        $events[0]->mealTypes()->where('id', '<>', $type->id)->delete();

        $this->seed(MealTypeSeeder::class);

        $this->assertSame(1, $events[0]->mealTypes()->count());
        $this->assertSame('Early breakfast', $type->fresh()->name);
        $this->assertSame('06:00:00', $type->fresh()->starts_at);
        $this->assertSame(4, $events[1]->mealTypes()->count());
        $this->assertTrue($events[1]->fresh()->isLocked());
    }
}
