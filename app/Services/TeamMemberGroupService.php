<?php

namespace App\Services;

use App\Models\Group;
use App\Models\TeamEngagement;
use Illuminate\Validation\ValidationException;

final class TeamMemberGroupService
{
    // Reuse only this model's loaded result; write services pass a freshly locked member.
    public function hasAssignments(TeamEngagement $member): bool
    {
        if (! array_key_exists('shift_assignments_exists', $member->getAttributes())) {
            $member->loadExists('shiftAssignments');
        }

        return (bool) $member->shift_assignments_exists;
    }

    public function required(string $status, ?TeamEngagement $member = null): bool
    {
        return $status === 'hired' || ($member !== null && $this->hasAssignments($member));
    }

    public function error(int $eventId, mixed $groupId, string $status, ?TeamEngagement $member = null): ?string
    {
        if ($groupId === null || $groupId === '') {
            return $this->required($status, $member)
                ? __('team.member.errors.group_required') : null;
        }

        return Group::query()->where('event_id', $eventId)->whereKey($groupId)->exists()
            ? null : __('team.member.errors.group_event');
    }

    public function ensure(int $eventId, mixed $groupId, string $status, ?TeamEngagement $member = null, string $field = 'group_id'): void
    {
        if (($error = $this->error($eventId, $groupId, $status, $member)) !== null) {
            throw ValidationException::withMessages([$field => $error]);
        }
    }
}
