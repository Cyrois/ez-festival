<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\OrganizationContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['person_id', 'name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->person_id === null || ! $user->isDirty('person_id')) {
                return;
            }

            $person = Person::query()->find($user->person_id);
            if ($person !== null) {
                $user->name = $person->name;
                $user->email = $person->email;
                $user->phone = $person->phone;
            }
        });

        static::created(function (User $user): void {
            if ($user->person_id !== null) {
                return;
            }

            $person = Person::query()->create([
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'can_log_in' => true,
            ]);

            $user->forceFill(['person_id' => $person->id])->saveQuietly();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'has_set_password' => 'boolean',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)->withTimestamps();
    }

    public function primaryOrganization(): ?Organization
    {
        return $this->organizations()->orderBy('organizations.id')->first();
    }

    /**
     * Ensure the user belongs to this client database's organization.
     */
    public function ensureOrganization(): Organization
    {
        $organization = $this->primaryOrganization();

        if ($organization !== null) {
            return $organization;
        }

        $organization = app(OrganizationContext::class)->organization();

        $this->organizations()->syncWithoutDetaching([$organization->id]);

        return $organization;
    }

    public function currentEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'current_event_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function loginInvitation(): HasOne
    {
        return $this->hasOne(LoginInvitation::class);
    }

    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'custom_fieldable');
    }

    public function effectiveEvent(): ?Event
    {
        $currentEventId = self::query()->whereKey($this->getKey())->value('current_event_id');

        if ($currentEventId !== null) {
            $current = $this->accessibleEvents()->whereKey($currentEventId)->first();

            if ($current !== null) {
                return $current;
            }
        }

        $defaultEventId = app(OrganizationContext::class)->defaultEvent()?->getKey();

        if ($defaultEventId !== null) {
            $default = $this->accessibleEvents()->whereKey($defaultEventId)->first();

            if ($default !== null) {
                return $default;
            }
        }

        return $this->accessibleEvents()
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->first();
    }

    public function canSignIn(): bool
    {
        $access = $this->accessIdentity();

        if ($access === null || ! (bool) $access->can_log_in) {
            return false;
        }

        return (bool) $access->is_admin
            || TeamEngagement::query()
                ->where('person_id', $access->access_person_id)
                ->whereHas('role', fn (Builder $query) => $query->where('active', true))
                ->exists();
    }

    public function isAdmin(): bool
    {
        return (bool) (self::query()->whereKey($this->getKey())->value('is_admin') ?? false);
    }

    public function canAccessEvent(Event|int $event): bool
    {
        return $this->accessibleEvents()->whereKey($event instanceof Event ? $event->getKey() : $event)->exists();
    }

    /** @return Builder<Event> */
    public function accessibleEvents(): Builder
    {
        $events = Event::query();
        $access = $this->accessIdentity();

        if ($access === null || ! (bool) $access->can_log_in) {
            return $events->whereRaw('1 = 0');
        }

        if ((bool) $access->is_admin) {
            return $events;
        }

        return $events->whereHas(
            'teamEngagements',
            fn (Builder $query) => $query
                ->where('person_id', $access->access_person_id)
                ->whereHas('role', fn (Builder $query) => $query->where('active', true)),
        );
    }

    public function setCurrentEvent(Event $event): void
    {
        $this->forceFill(['current_event_id' => $event->id])->save();
    }

    private function accessIdentity(): ?object
    {
        return self::query()
            ->leftJoin('people', 'people.id', '=', 'users.person_id')
            ->where('users.id', $this->getKey())
            ->first([
                'users.is_admin',
                'people.can_log_in',
                'people.id as access_person_id',
            ]);
    }
}
