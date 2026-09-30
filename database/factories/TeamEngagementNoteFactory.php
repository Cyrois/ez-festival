<?php

namespace Database\Factories;

use App\Models\TeamEngagementNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeamEngagementNote> */
class TeamEngagementNoteFactory extends Factory
{
    protected $model = TeamEngagementNote::class;

    public function definition(): array
    {
        return [
            'body' => fake()->sentence(),
        ];
    }
}
