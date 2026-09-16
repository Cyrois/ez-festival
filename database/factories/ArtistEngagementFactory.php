<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArtistEngagementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'artist_id' => Artist::factory(),
            'event_id' => fn () => Event::query()->create([
                'name' => fake()->words(3, true),
                'starts_on' => '2027-06-01',
                'ends_on' => '2027-06-03',
                'timezone' => 'America/Vancouver',
            ])->id,
            'status' => 'idea',
        ];
    }
}
