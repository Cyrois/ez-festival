<?php

namespace Database\Factories;

use App\Models\VendorEngagementNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VendorEngagementNote> */
class VendorEngagementNoteFactory extends Factory
{
    protected $model = VendorEngagementNote::class;

    public function definition(): array
    {
        return ['body' => fake()->sentence()];
    }
}
