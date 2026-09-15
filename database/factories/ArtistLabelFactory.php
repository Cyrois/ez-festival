<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArtistLabelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => fn () => Organization::query()->create(['name' => fake()->company()])->id,
            'name' => fake()->unique()->word(),
            'color' => 'primary',
        ];
    }
}
