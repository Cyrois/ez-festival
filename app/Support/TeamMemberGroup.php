<?php

namespace App\Support;

use App\Models\Group;
use App\Models\TeamEngagement;
use Illuminate\Validation\ValidationException;

final class TeamMemberGroup
{
    public static function error(int $eventId, mixed $groupId, string $status, ?TeamEngagement $member = null): ?string
    {
        if ($groupId === null || $groupId === '') {
            return $status === 'hired' || ($member !== null && $member->shiftAssignments()->exists())
                ? __('team.member.errors.group_required') : null;
        }

        return Group::query()->where('event_id', $eventId)->whereKey($groupId)->exists()
            ? null : __('team.member.errors.group_event');
    }

    public static function ensure(int $eventId, mixed $groupId, string $status, ?TeamEngagement $member = null, string $field = 'group_id'): void
    {
        if (($error = self::error($eventId, $groupId, $status, $member)) !== null) {
            throw ValidationException::withMessages([$field => $error]);
        }
    }
}
