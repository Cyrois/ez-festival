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
    /**
     * Resolve effective permissions from the person's active role at this event.
     * Default to the user's effective event when none is supplied, and include
     * implied permissions (for example, team.edit also grants team.view).
     * Admins receive every permission. Cache only within the current request so
     * role changes and deactivation take effect on the next request.
     *
     * @return array<int, string>
     */
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

    /**
     * Check a permission at the supplied event, or the person's effective event.
     * This checks role access; write paths must also enforce the event lock.
     */
    public function allows(User $user, string $permission, ?Event $event = null): bool
    {
        return in_array($permission, $this->permissions($user, $event), true);
    }

    /**
     * Return every known permission as a true/false flag for frontend controls
     * at the supplied event, or the user's effective event when omitted.
     *
     * @return array<string, bool>
     */
    public function map(User $user, ?Event $event = null): array
    {
        $permissions = $this->permissions($user, $event);

        return array_combine(Permissions::keys(), array_map(fn ($key) => in_array($key, $permissions, true), Permissions::keys()));
    }

    /**
     * Allow role changes only for another person, with team.change_role access.
     * A non-admin may assign an active role only when its effective permissions
     * are a subset of their own at the member's event. Null removes the role.
     * This prevents granting access the person making the change does not have.
     * Admins bypass these role restrictions, but event locks still apply.
     */
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
