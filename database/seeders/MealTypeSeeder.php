<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Support\MealTypeDefaults;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MealTypeSeeder extends Seeder
{
    public function run(): void
    {
        Event::query()->select('id')->chunkById(200, function ($events): void {
            foreach ($events as $event) {
                DB::transaction(function () use ($event): void {
                    $event = Event::query()->lockForUpdate()->findOrFail($event->id);

                    // Preserve configured events, including renamed types and windows.
                    if ($event->mealTypes()->exists()) {
                        return;
                    }

                    DB::table('meal_types')->insert(MealTypeDefaults::rows($event->id));
                }, 3);
            }
        });
    }
}
