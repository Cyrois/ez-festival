<?php

namespace App\Services;

use App\Models\Person;

class PersonService
{
    /** @param array{name: string, email?: string|null, phone?: string|null} $data */
    public function findOrCreateByEmail(array $data): Person
    {
        $email = $this->normalizeEmail($data['email'] ?? null);
        $person = $email !== null ? $this->findByEmail($email) : null;
        $person ??= new Person(['email' => $email]);

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

    public function findByEmail(?string $email): ?Person
    {
        $email = $this->normalizeEmail($email);

        if ($email === null) {
            return null;
        }

        return Person::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->first();
    }

    /** @param array{name: string, phone?: string|null} $data */
    private function fillNameAndPhone(Person $person, array $data): void
    {
        $person->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);
    }

    public function normalizeEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email === '' ? null : mb_strtolower($email);
    }
}
