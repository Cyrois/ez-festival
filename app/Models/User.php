<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('current_event_id', 'can_manage_artists')
            ->withTimestamps();
    }

    public function primaryOrganization(): ?Organization
    {
        return $this->organizations()->orderBy('organizations.id')->first();
    }

    /**
     * Ensure the user belongs to an organization, creating one if needed.
     */
    public function ensureOrganization(): Organization
    {
        $organization = $this->primaryOrganization();

        if ($organization !== null) {
            return $organization;
        }

        $name = filled($this->name) ? $this->name."'s organization" : 'My organization';

        $organization = Organization::query()->create([
            'name' => $name,
        ]);

        $this->organizations()->attach($organization, ['can_manage_artists' => true]);

        return $organization;
    }

    /**
     * Resolve the effective event for this user in an organization:
     * membership current_event_id (if still valid for the org), else org active_event_id.
     */
    public function effectiveEvent(?Organization $organization = null): ?Event
    {
        $organization ??= $this->primaryOrganization();

        if ($organization === null) {
            return null;
        }

        $membership = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first();

        $overrideId = $membership?->pivot?->current_event_id;

        if ($overrideId !== null) {
            $override = $organization->events()->whereKey($overrideId)->first();

            if ($override !== null) {
                return $override;
            }
        }

        return $organization->activeEvent;
    }

    /**
     * Set this user's per-org current event override.
     */
    public function setCurrentEvent(Organization $organization, Event $event): void
    {
        $this->organizations()->updateExistingPivot($organization->id, [
            'current_event_id' => $event->id,
        ]);
    }
}
