<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;

final class EventAccessService
{
    public function permissions(User $user, ?Event $event = null): array
    {
        if ($user->isAdmin()) {
            return Permissions::keys();
        }
        $event ??= $user->effectiveEvent();
        if ($event === null) {
            return [];
        }
        $request = app(Request::class);
        $key = 'event_permissions_'.$user->id.'_'.$event->id;
        if (! $request->attributes->has($key)) {
            $engagement = TeamEngagement::query()->with('role')
                ->where('event_id', $event->id)->where('person_id', $user->person_id)->first();
            $request->attributes->set($key, $engagement?->role?->active
                ? Permissions::expand($engagement->role->permissions ?? []) : []);
        }

        return $request->attributes->get($key);
    }

    public function allows(User $user, string $permission, ?Event $event = null): bool
    {
        return in_array($permission, $this->permissions($user, $event), true);
    }

    public function map(User $user, ?Event $event = null): array
    {
        $permissions = $this->permissions($user, $event);

        return array_combine(Permissions::keys(), array_map(fn ($key) => in_array($key, $permissions, true), Permissions::keys()));
    }

    public function canAssignRole(User $user, TeamEngagement $member, ?Role $role): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $member->person_id !== $user->person_id
            && $this->allows($user, 'team.change_role', $member->event)
            && ($role === null || ($role->active && array_diff(Permissions::expand($role->permissions ?? []), $this->permissions($user, $member->event)) === []));
    }
}
