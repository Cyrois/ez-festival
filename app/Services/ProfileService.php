<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    /** @param array{name: string, email?: string|null, phone?: string|null} $data */
    public function update(Person $person, array $data): void
    {
        DB::transaction(function () use ($person, $data): void {
            $person = Person::query()->lockForUpdate()->findOrFail($person->id);
            $email = app(PersonService::class)->normalizeEmail($data['email'] ?? $person->email);
            if ($email !== null && (Person::query()->whereKeyNot($person->id)->whereRaw('lower(email) = ?', [$email])->exists()
                || User::query()->where('person_id', '!=', $person->id)->whereRaw('lower(email) = ?', [$email])->exists())) {
                throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
            }
            $profile = ['name' => $data['name'], 'email' => $email, 'phone' => $data['phone'] ?? null];
            $person->update($profile);
            foreach (User::query()->where('person_id', $person->id)->lockForUpdate()->get() as $user) {
                $user->update($profile);
            }
        });
    }
}
