<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $organization = app(OrganizationContext::class)->organization();

        $user = User::query()->updateOrCreate(
            ['email' => 'calvinkylechan@gmail.com'],
            [
                'name' => 'Calvin Kyle Chan',
                'password' => 'test1234!',
                'email_verified_at' => now(),
            ],
        );
        $user->forceFill(['is_admin' => true])->saveQuietly();

        $person = $user->person ?? Person::query()->create([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);
        $person->update([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'can_log_in' => true,
        ]);

        if ($user->person_id !== $person->id) {
            $user->forceFill(['person_id' => $person->id])->saveQuietly();
        }

        $user->organizations()->syncWithoutDetaching([$organization->id]);
    }
}
