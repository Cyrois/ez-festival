<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GlobalTeamService
{
    public function __construct(private readonly PersonService $people) {}

    public function loginLockReason(User $actor, Person $person): ?string
    {
        if (! $person->can_log_in) {
            return null;
        }

        if ((int) $actor->person_id === (int) $person->id) {
            return __('settings.team.login.own_disabled');
        }

        return $person->user()->where('is_admin', true)->exists()
            ? __('settings.team.login.admin_disabled')
            : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Person
    {
        return DB::transaction(function () use ($data): Person {
            if ($this->people->findByEmail($data['email']) !== null) {
                throw ValidationException::withMessages([
                    'email' => __('settings.team.validation.email_exists'),
                ]);
            }

            $person = Person::query()->create([
                'name' => $data['name'],
                'email' => $this->people->normalizeEmail($data['email']),
                'phone' => $this->normalizePhone($data['phone'] ?? null),
                'can_log_in' => $data['can_log_in'],
            ]);

            foreach ($data['event_access'] as $access) {
                $event = Event::query()->lockForUpdate()->findOrFail($access['event_id']);
                $event->ensureWritable();
                $role = Role::query()->lockForUpdate()->findOrFail($access['role_id']);

                if (! $role->active) {
                    throw ValidationException::withMessages([
                        'event_access' => __('settings.team.validation.role_unavailable'),
                    ]);
                }

                $engagement = TeamEngagement::query()
                    ->whereBelongsTo($event)
                    ->whereBelongsTo($person)
                    ->lockForUpdate()
                    ->first();

                if ($engagement) {
                    $engagement->update([
                        'role_id' => $role->id,
                        'status' => $data['status'],
                    ]);
                } else {
                    TeamEngagement::query()->create([
                        'event_id' => $event->id,
                        'person_id' => $person->id,
                        'role_id' => $role->id,
                        'status' => $data['status'],
                        'employment_type' => 'volunteer',
                    ]);
                }
            }

            return $person;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Person $person, array $data): void
    {
        DB::transaction(function () use ($person, $data): void {
            $person = Person::query()->lockForUpdate()->findOrFail($person->id);
            $person->update([
                'name' => $data['name'],
                'phone' => $this->normalizePhone($data['phone'] ?? null),
                'can_log_in' => $data['can_log_in'],
            ]);

            foreach ($data['event_access'] as $index => $access) {
                $event = Event::query()->lockForUpdate()->findOrFail($access['event_id']);
                $engagement = TeamEngagement::query()
                    ->whereBelongsTo($event)
                    ->whereBelongsTo($person)
                    ->lockForUpdate()
                    ->first();
                $currentRoleId = $engagement?->role_id;
                $roleId = $access['role_id'] ?? null;

                if ($event->isLocked() && (int) $currentRoleId !== (int) $roleId) {
                    throw ValidationException::withMessages([
                        "event_access.{$index}.role_id" => __('settings.team.validation.locked_event'),
                    ]);
                }

                if ($event->isLocked() || (int) $currentRoleId === (int) $roleId) {
                    continue;
                }

                if ($roleId === null) {
                    $engagement?->update(['role_id' => null]);

                    continue;
                }

                $role = Role::query()->lockForUpdate()->findOrFail($roleId);
                if (! $role->active) {
                    throw ValidationException::withMessages([
                        "event_access.{$index}.role_id" => __('settings.team.validation.role_unavailable'),
                    ]);
                }

                if ($currentRoleId === null) {
                    $status = $access['status'] ?? null;
                    if (! in_array($status, ['applied', 'reviewing', 'hired'], true)) {
                        throw ValidationException::withMessages([
                            "event_access.{$index}.status" => __('settings.team.validation.status_required'),
                        ]);
                    }

                    if ($engagement) {
                        $engagement->update(['role_id' => $role->id, 'status' => $status]);
                    } else {
                        TeamEngagement::query()->create([
                            'event_id' => $event->id,
                            'person_id' => $person->id,
                            'role_id' => $role->id,
                            'status' => $status,
                            'employment_type' => 'volunteer',
                        ]);
                    }

                    continue;
                }

                $engagement->update(['role_id' => $role->id]);
            }
        });
    }

    private function normalizePhone(mixed $phone): ?string
    {
        $phone = trim((string) $phone);

        return $phone === '' ? null : $phone;
    }
}
