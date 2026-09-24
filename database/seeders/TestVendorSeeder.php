<?php

namespace Database\Seeders;

use App\Models\ExpectedEntitlement;
use App\Models\Organization;
use App\Models\PassType;
use App\Models\Person;
use App\Models\Vendor;
use App\Models\VendorType;
use Illuminate\Database\Seeder;

class TestVendorSeeder extends Seeder
{
    public function run(): void
    {
        $event = Organization::query()->firstOrFail()->activeEvent()->firstOrFail();
        $vendors = [
            ['Cedar Craft Co', 'Artisan', 'Priya Nair', 'priya@example.com', 'Vendor staff'],
            ['Night Owl Foods', 'Food & Beverage', 'Sam Torres', 'sam@example.com', 'Vendor staff'],
            ['Horizon Production', 'Production', 'Taylor Reed', 'taylor@example.com', 'All access'],
        ];

        foreach ($vendors as [$name, $typeName, $personName, $email, $passName]) {
            $type = VendorType::query()->where('name', $typeName)->firstOrFail();
            $vendor = Vendor::query()->firstOrCreate(
                ['name_key' => Vendor::normalizeName($name)],
                ['name' => $name],
            );
            $engagement = $vendor->engagements()->updateOrCreate(
                ['event_id' => $event->id],
                ['vendor_type_id' => $type->id, 'status' => 'confirmed'],
            );
            $person = Person::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $personName],
            );
            $engagement->people()->syncWithoutDetaching([
                $person->id => ['is_primary' => true],
            ]);

            $pass = PassType::query()
                ->where('event_id', $event->id)
                ->where('name_key', PassType::normalizeName($passName))
                ->with('entitlements')
                ->firstOrFail();
            $assignment = $engagement->passAssignments()->firstOrCreate([
                'pass_type_id' => $pass->id,
                'person_id' => $person->id,
            ]);

            foreach ($pass->entitlements as $entitlement) {
                $assignment->expectedEntitlements()->firstOrCreate([
                    'entitlement_item_id' => $entitlement->entitlement_item_id,
                ], ['status' => ExpectedEntitlement::STATUS_EXPECTED]);
            }
        }
    }
}
