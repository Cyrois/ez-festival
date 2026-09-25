<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Person;
use App\Models\TeamEngagement;
use Illuminate\Database\Seeder;

class TestTeamSeeder extends Seeder
{
    public function run(): void
    {
        $event = Organization::query()->firstOrFail()->activeEvent()->firstOrFail();
        $groups = collect([
            'Stage',
            'Gate',
            'Headquarters',
        ])->mapWithKeys(function (string $name) use ($event): array {
            $group = $event->groups()->firstOrCreate(['name' => $name]);

            return [$name => $group];
        });

        $members = [
            [
                'name' => 'Morgan West',
                'email' => 'morgan.west@example.com',
                'phone' => '(604) 555-0142',
                'group' => 'Gate',
                'status' => 'hired',
                'employment_type' => 'paid',
                'hourly_pay' => 28,
            ],
            [
                'name' => 'Taylor Brooks',
                'email' => 'taylor.brooks@example.com',
                'phone' => '(604) 555-0168',
                'group' => 'Stage',
                'status' => 'reviewing',
                'employment_type' => 'volunteer',
                'hourly_pay' => null,
            ],
            [
                'name' => 'Riley Jones',
                'email' => 'riley.jones@example.com',
                'phone' => '(604) 555-0193',
                'group' => 'Headquarters',
                'status' => 'applied',
                'employment_type' => 'paid',
                'hourly_pay' => 25,
            ],
        ];

        foreach ($members as $member) {
            $person = Person::query()->updateOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['name'],
                    'phone' => $member['phone'],
                ],
            );

            TeamEngagement::query()->updateOrCreate(
                [
                    'event_id' => $event->id,
                    'person_id' => $person->id,
                ],
                [
                    'group_id' => $groups[$member['group']]->id,
                    'status' => $member['status'],
                    'employment_type' => $member['employment_type'],
                    'hourly_pay' => $member['hourly_pay'],
                ],
            );
        }
    }
}
