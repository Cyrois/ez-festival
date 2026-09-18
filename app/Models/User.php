<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\OrganizationContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password'])]
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

    public function effectiveEvent(): ?Event
    {
        return $this->currentEvent()->first() ?? app(OrganizationContext::class)->defaultEvent();
    }

    public function setCurrentEvent(Event $event): void
    {
        $this->forceFill(['current_event_id' => $event->id])->save();
    }
}
