<?php

namespace Database\Seeders;

use App\Models\Artist;
use App\Models\ArtistType;
use App\Models\ExpectedEntitlement;
use App\Models\Organization;
use App\Models\PassType;
use App\Models\Person;
use Illuminate\Database\Seeder;

class TestArtistSeeder extends Seeder
{
    public function run(): void
    {
        $event = Organization::query()->firstOrFail()->activeEvent()->firstOrFail();
        $artists = [
            ['River Hollow', 'Headliner', 'Maya Chen', 'maya@example.com', 'Artist pass'],
            ['Luna Vale', 'Support', 'Alex Kim', 'alex@example.com', 'All access'],
            ['Cedar Sky', 'DJ', 'Riley Quinn', 'riley@example.com', 'Artist pass'],
        ];

        foreach ($artists as [$name, $typeName, $personName, $email, $passName]) {
            $type = ArtistType::query()->where('name', $typeName)->firstOrFail();
            $artist = Artist::query()->firstOrCreate(
                ['name_key' => Artist::normalizeName($name)],
                ['name' => $name],
            );
            $engagement = $artist->engagements()->updateOrCreate(
                ['event_id' => $event->id],
                ['artist_type_id' => $type->id, 'status' => 'confirmed'],
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
