<?php

namespace Database\Seeders;

use App\Models\ArtistType;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Models\VendorType;
use Illuminate\Database\Seeder;

class TestSetupSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->firstOrFail();
        $event = $organization->activeEvent()->first()
            ?? Event::query()->firstOrCreate(
                ['name' => 'Sunrise Folk Fest 2026'],
                [
                    'starts_on' => '2026-07-10',
                    'ends_on' => '2026-07-12',
                    'timezone' => 'America/Vancouver',
                ],
            );

        $organization->forceFill([
            'active_event_id' => $event->id,
            'setup_completed_at' => $organization->setup_completed_at ?? now(),
        ])->save();

        User::query()
            ->where('email', 'calvinkylechan@gmail.com')
            ->firstOrFail()
            ->setCurrentEvent($event);

        foreach ([
            ['name' => 'Main Gate', 'type' => 'Gate'],
            ['name' => 'Artist Check-in', 'type' => 'Backstage'],
            ['name' => 'Vendor Check-in', 'type' => 'Operations'],
        ] as $location) {
            $event->locations()->updateOrCreate(
                ['name' => $location['name']],
                ['type' => $location['type']],
            );
        }

        foreach (['Headliner', 'Support', 'DJ'] as $sortOrder => $name) {
            ArtistType::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $sortOrder],
            );
        }

        foreach (['Food & Beverage', 'Artisan', 'Production'] as $sortOrder => $name) {
            VendorType::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $sortOrder],
            );
        }
    }
}
