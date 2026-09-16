<?php

namespace Database\Factories;

use App\Models\ArtistEngagement;
use App\Models\ArtistEngagementNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArtistEngagementNote>
 */
class ArtistEngagementNoteFactory extends Factory
{
    protected $model = ArtistEngagementNote::class;

    public function definition(): array
    {
        return [
            'artist_engagement_id' => ArtistEngagement::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
