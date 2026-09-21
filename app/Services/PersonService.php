<?php

namespace App\Services;

use App\Models\Person;

class PersonService
{
    /** @param array{name: string, email: string, phone?: string|null} $data */
    public function findOrCreateByEmail(array $data): Person
    {
        $person = Person::query()->firstOrNew([
            'email' => $this->normalizeEmail($data['email']),
        ]);

        $this->fillNameAndPhone($person, $data);
        $person->save();

        return $person;
    }

    /** @param array{name: string, email: string, phone?: string|null} $data */
    public function updateProfile(Person $person, array $data): void
    {
        $person->email = $this->normalizeEmail($data['email']);
        $this->fillNameAndPhone($person, $data);
        $person->save();
    }

    /** @param array{name: string, phone?: string|null} $data */
    private function fillNameAndPhone(Person $person, array $data): void
    {
        $person->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
