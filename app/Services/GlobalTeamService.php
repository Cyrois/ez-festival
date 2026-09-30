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
    public function __construct(
        private readonly PersonService $people,
        private readonly LoginInvitationService $invitations,
    ) {}

    public function loginLockReason(User $actor, Person $person): ?string
    {
        if (! $person->can_log_in) {
            return null;
        }

        if ((int) $actor->person_id === (int) $person->id) {
            return __('settings.team.login.own_disabled');
        }

        return null;
    }

    public function adminLockReason(User $actor, Person $person): ?string
    {
        $target = $person->user()->first();

        if ($target === null || ! $target->is_admin) {
            return null;
        }

        return (int) $actor->getKey() === (int) $target->getKey()
            ? __('settings.team.admin.own_disabled')
            : null;
    }

    public function adminChangeError(
        User $actor,
        ?User $target,
        bool $isAdmin,
        ?bool $canLogIn = null,
    ): ?string {
        if (! $actor->isAdmin()) {
            return __('settings.team.admin.unauthorized');
        }

        $loginEnabled = $canLogIn
            ?? ($target !== null && $target->person()->where('can_log_in', true)->exists());

        if ($isAdmin && (! $loginEnabled || ($target === null && $canLogIn !== true))) {
            return __('settings.team.admin.login_required');
        }

        if (! $isAdmin && $target !== null && (int) $actor->getKey() === (int) $target->getKey()) {
            return __('settings.team.admin.own_disabled');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{person: Person, invite_sent: ?bool}
     */
    public function create(array $data, User $actor): array
    {
        $person = DB::transaction(function () use ($data): Person {
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

        return [
            'person' => $person,
            'invite_sent' => $person->can_log_in
                ? $this->invitations->send($person, $actor)
                : null,
        ];
    }

    /** @param array<string, mixed> $data */
    public function update(Person $person, array $data, User $actor): ?bool
    {
        $loginChange = DB::transaction(function () use ($person, $data, $actor): ?string {
            if (array_key_exists('is_admin', $data)) {
                User::query()
                    ->where('is_admin', true)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);
            }

            $person = Person::query()->lockForUpdate()->findOrFail($person->id);
            $target = User::query()
                ->where('person_id', $person->id)
                ->lockForUpdate()
                ->first();
            $wasAdmin = (bool) $target?->is_admin;
            $willBeAdmin = array_key_exists('is_admin', $data)
                ? (bool) $data['is_admin']
                : $wasAdmin;

            $wasEnabled = $person->can_log_in;
            $person->update([
                'name' => $data['name'],
                'phone' => $this->normalizePhone($data['phone'] ?? null),
                'can_log_in' => $data['can_log_in'],
            ]);

            if ($willBeAdmin && $target === null) {
                $target = $this->invitations->ensureLogin($person);
            }

            if ($willBeAdmin !== $wasAdmin) {
                $this->changeAdminAccess($actor, $target, $willBeAdmin);
            }

            foreach ($data['event_access'] as $index => $access) {
                $event = Event::query()->lockForUpdate()->findOrFail($access['event_id']);
                $engagement = TeamEngagement::query()
                    ->whereBelongsTo($event)
                    ->whereBelongsTo($person)
                    ->lockForUpdate()
                    ->first();
                $currentRoleId = $engagement?->role_id;
                $roleId = $access['role_id'] ?? null;

                if ($wasAdmin && $willBeAdmin && (int) $currentRoleId !== (int) $roleId) {
                    throw ValidationException::withMessages([
                        "event_access.{$index}.role_id" => __('settings.team.admin.event_access_locked'),
                    ]);
                }

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

            if ($wasEnabled === $person->can_log_in) {
                return null;
            }

            return $person->can_log_in ? 'enabled' : 'disabled';
        });

        $person->refresh();
        if ($loginChange === 'disabled') {
            $this->invitations->cancel($person);
        } elseif ($loginChange === 'enabled' && ! $this->invitations->hasEverSetPassword($person)) {
            return $this->invitations->send($person, $actor);
        }

        return null;
    }

    private function changeAdminAccess(User $actor, ?User $target, bool $isAdmin): void
    {
        $error = $this->adminChangeError($actor, $target, $isAdmin);

        if ($error !== null) {
            throw ValidationException::withMessages([
                'is_admin' => $error,
            ]);
        }

        /** @var User $target */
        if (! $isAdmin) {
            $admins = User::query()
                ->where('is_admin', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($admins->count() <= 1) {
                throw ValidationException::withMessages([
                    'is_admin' => __('settings.team.admin.last_required'),
                ]);
            }
        }

        $target->forceFill(['is_admin' => $isAdmin])->save();
    }

    private function normalizePhone(mixed $phone): ?string
    {
        $phone = trim((string) $phone);

        return $phone === '' ? null : $phone;
    }
}
