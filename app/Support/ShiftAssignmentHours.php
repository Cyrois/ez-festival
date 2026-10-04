<?php

namespace App\Support;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class ShiftAssignmentHours
{
    public static function scheduledMinutes(ShiftAssignment $assignment, int $breakMinutes): int
    {
        // Stored times are event-local wall clocks. UTC parsing avoids introducing DST/browser offsets.
        $start = CarbonImmutable::parse($assignment->starts_at->format('Y-m-d H:i:s'), 'UTC');
        $end = CarbonImmutable::parse($assignment->ends_at->format('Y-m-d H:i:s'), 'UTC');

        return max(0, (int) $start->diffInMinutes($end) - $breakMinutes);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    public static function resolve(Shift $shift, array $data): array
    {
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
        $names = $shift->assignments()
            ->where(fn ($query) => $query->where('starts_at', '<', CarbonImmutable::parse($data['starts_at']))
                ->orWhere('ends_at', '>', CarbonImmutable::parse($data['ends_at'])))
            ->join('team_engagements', 'team_engagements.id', '=', 'shift_assignments.team_engagement_id')
            ->join('people', 'people.id', '=', 'team_engagements.person_id')
            ->orderBy('shift_assignments.id')->pluck('people.name')->unique();

        return $names->isEmpty() ? [] : ['ends_at' => __('team.scheduling.assignments.errors.containment', ['names' => $names->implode(', ')])];
    }
}
