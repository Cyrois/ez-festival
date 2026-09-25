<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['id' => 1],
            ['name' => 'Festival'],
        );

        $user = User::query()->updateOrCreate(
            ['email' => 'calvinkylechan@gmail.com'],
            [
                'name' => 'Calvin Kyle Chan',
                'password' => 'test1234!',
                'email_verified_at' => now(),
            ],
        );

        $person = $user->person ?? Person::query()->create([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);
        $person->update([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);

        if ($user->person_id !== $person->id) {
            $user->forceFill(['person_id' => $person->id])->saveQuietly();
        }

        $user->organizations()->syncWithoutDetaching([$organization->id]);
    }
}
