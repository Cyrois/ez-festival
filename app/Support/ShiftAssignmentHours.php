<?php

namespace App\Support;

use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class ShiftAssignmentHours
{
    /** @return array{CarbonImmutable, CarbonImmutable} */
    public static function resolve(Shift $shift, array $data): array
    {
        $start = CarbonImmutable::parse($data['hours_mode'] === 'full_shift' ? $shift->starts_at : $data['starts_at']);
        $end = CarbonImmutable::parse($data['hours_mode'] === 'full_shift' ? $shift->ends_at : $data['ends_at']);
        if ($start->lt($shift->starts_at) || $end->gt($shift->ends_at) || ! $start->lt($end)) {
            throw ValidationException::withMessages(['ends_at' => __('team.scheduling.assignments.errors.hours')]);
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
