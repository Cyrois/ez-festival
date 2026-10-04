<?php

namespace App\Services;

use App\Models\ArtistEngagement;
use App\Models\Person;
use App\Models\VendorEngagement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonService
{
    /** @param array{name: string, email?: string|null, phone?: string|null} $data */
    public function findOrCreateByEmail(array $data): Person
    {
        $email = $this->normalizeEmail($data['email'] ?? null);
        if ($email !== null && ($person = $this->findByEmail($email)) !== null) {
            return $person;
        }
        try {
            // The savepoint lets PostgreSQL recover safely from a concurrent unique-key conflict.
            return DB::transaction(fn (): Person => Person::create([
                'email' => $email, 'name' => $data['name'], 'phone' => $data['phone'] ?? null,
            ]));
        } catch (UniqueConstraintViolationException $exception) {
            if ($email !== null && ($person = $this->findByEmail($email)) !== null) {
                return $person;
            }
            throw $exception;
        }
    }

    /** @param array{name: string, email: string, phone?: string|null} $data */
    public function updateProfile(Person $person, array $data): void
    {
        app(ProfileService::class)->update($person, $data);
    }

    public function findByEmail(?string $email): ?Person
    {
        $email = $this->normalizeEmail($email);

        if ($email === null) {
            return null;
        }

        return Person::query()
            ->whereRaw('lower(trim(email)) = ?', [$email])
            ->first();
    }

    /** @param array{name: string, email?: string|null, phone?: string|null} $data */
    public function updateContactProfile(Person $person, ArtistEngagement|VendorEngagement $owner, array $data): void
    {
        $person = Person::query()->lockForUpdate()->findOrFail($person->id);
        $profile = ['name' => $data['name'], 'email' => $this->normalizeEmail($data['email'] ?? null), 'phone' => ($data['phone'] ?? null) ?: null];
        $changed = $person->name !== $profile['name'] || $this->normalizeEmail($person->email) !== $profile['email'] || $person->phone !== $profile['phone'];
        $ownerColumn = $owner instanceof ArtistEngagement ? 'artist_engagement_id' : 'vendor_engagement_id';
        $shared = $person->user()->exists() || $person->teamEngagements()->exists() || $person->eventPatrons()->exists()
            || $person->artistEngagements()->when($owner instanceof ArtistEngagement, fn ($query) => $query->whereKeyNot($owner->id))->exists()
            || $person->vendorEngagements()->when($owner instanceof VendorEngagement, fn ($query) => $query->whereKeyNot($owner->id))->exists()
            || $person->passAssignments()->where(fn ($query) => $query->whereNull($ownerColumn)->orWhere($ownerColumn, '!=', $owner->id))->exists();
        if ($changed && $shared) {
            throw ValidationException::withMessages(['email' => __('contacts.errors.shared_profile')]);
        }
        if ($changed) {
            $this->updateProfile($person, $profile);
        }
    }

    public function normalizeEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email === '' ? null : mb_strtolower($email);
    }
}
