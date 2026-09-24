<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\PassType;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $event = Organization::query()->firstOrFail()->activeEvent()->firstOrFail();
        $user = User::query()->where('email', 'calvinkylechan@gmail.com')->firstOrFail();
        $locations = $event->locations()->get();

        $items = collect(['Festival wristband', 'Meal voucher', 'Welcome pack'])
            ->mapWithKeys(fn (string $name) => [
                $name => $event->entitlementItems()->firstOrCreate(['name' => $name]),
            ]);

        foreach ($items as $item) {
            foreach ($locations as $location) {
                $item->adjustments()->updateOrCreate(
                    ['location_id' => $location->id, 'reason' => 'Seeded opening balance'],
                    ['delta' => 100, 'user_id' => $user->id],
                );
            }
        }

        $passes = [
            'Artist pass' => ['Festival wristband', 'Welcome pack'],
            'Vendor staff' => ['Festival wristband', 'Meal voucher'],
            'All access' => ['Festival wristband', 'Meal voucher', 'Welcome pack'],
        ];

        foreach ($passes as $name => $entitlements) {
            $pass = PassType::query()->updateOrCreate(
                ['event_id' => $event->id, 'name_key' => PassType::normalizeName($name)],
                ['name' => $name, 'max_assignments' => 100],
            );

            foreach ($entitlements as $sortOrder => $entitlement) {
                $pass->entitlements()->updateOrCreate(
                    ['entitlement_item_id' => $items[$entitlement]->id],
                    ['sort_order' => $sortOrder],
                );
            }
        }
    }
}
