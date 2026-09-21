<?php

namespace App\Services;

use App\Models\Person;

class PersonService
{
    /** @param array{name: string, email: string, phone?: string|null} $data */
    public function findOrCreateByEmail(array $data): Person
    {
        $email = mb_strtolower(trim($data['email']));

        return Person::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $data['name'], 'phone' => $data['phone'] ?? null],
        );
    }

    /** @param array{name: string, email: string, phone?: string|null} $data */
    public function updateProfile(Person $person, array $data): void
    {
        $person->update([
            'name' => $data['name'],
            'email' => mb_strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
        ]);
    }
}
