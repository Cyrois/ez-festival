<?php

namespace App\Support;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class ShiftAssignmentHours
{
    public static function scheduledMinutes(ShiftAssignment $assignment, int $breakMinutes, string $timezone = 'UTC'): int
    {
        return max(0, EventLocalTime::elapsedMinutes($assignment->starts_at, $assignment->ends_at, $timezone) - $breakMinutes);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    public static function resolve(Shift $shift, array $data): array
    {
        $timezone = $shift->event->timezone;
        EventLocalTime::validate($data['hours_mode'] === 'full_shift' ? ['starts_at' => $shift->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $shift->ends_at->format('Y-m-d\TH:i')] : $data, $timezone);
        $start = CarbonImmutable::parse($data['hours_mode'] === 'full_shift' ? $shift->starts_at : $data['starts_at']);
        $end = CarbonImmutable::parse($data['hours_mode'] === 'full_shift' ? $shift->ends_at : $data['ends_at']);
        if ($start->lt($shift->starts_at) || $end->gt($shift->ends_at) || ! $start->lt($end)) {
            throw ValidationException::withMessages(['ends_at' => __('team.scheduling.assignments.errors.hours', [
                'from' => $shift->starts_at->format('Y-m-d H:i'), 'to' => $shift->ends_at->format('Y-m-d H:i'),
            ])]);
        }

        return [$start, $end];
    }

    /** @return array<string, string> */
    public static function containmentErrors(Shift $shift, array $data): array
    {
        $changedIds = [...($data['assignment_removals'] ?? []), ...array_column($data['assignment_updates'] ?? [], 'id')];
        $names = $shift->assignments()->whereNotIn('shift_assignments.id', $changedIds)
            ->where(fn ($query) => $query->where('starts_at', '<', CarbonImmutable::parse($data['starts_at']))
                ->orWhere('ends_at', '>', CarbonImmutable::parse($data['ends_at'])))
            ->join('team_engagements', 'team_engagements.id', '=', 'shift_assignments.team_engagement_id')
            ->join('people', 'people.id', '=', 'team_engagements.person_id')
            ->orderBy('shift_assignments.id')->pluck('people.name')->unique();

        return $names->isEmpty() ? [] : ['ends_at' => __('team.scheduling.assignments.errors.containment', ['names' => $names->implode(', ')])];
    }
}
