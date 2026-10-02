<?php

namespace App\Support;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class ShiftAssignmentOverlaps
{
    public static function forMembers(Shift $shift, array $memberIds, $start, $end): Collection
    {
        if ($memberIds === []) {
            return collect();
        }

        return ShiftAssignment::query()->whereIn('team_engagement_id', $memberIds)
            ->where('shift_id', '!=', $shift->id)
            ->whereHas('shift', fn ($query) => $query->where('event_id', $shift->event_id))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->with('shift:id,name')->orderBy('starts_at')->orderBy('id')->get()
            ->groupBy('team_engagement_id');
    }

    public static function warnings(Collection $others, $start, $end, string $timezone): array
    {
        $start = CarbonImmutable::parse($start->format('Y-m-d H:i:s'), $timezone);
        $end = CarbonImmutable::parse($end->format('Y-m-d H:i:s'), $timezone);

        return $others->filter(fn ($other) => $other->starts_at->format('Y-m-d H:i:s') < $end->format('Y-m-d H:i:s')
            && $other->ends_at->format('Y-m-d H:i:s') > $start->format('Y-m-d H:i:s'))
            ->map(function ($other) use ($start, $end, $timezone): array {
                $otherStart = CarbonImmutable::parse($other->starts_at->format('Y-m-d H:i:s'), $timezone);
                $otherEnd = CarbonImmutable::parse($other->ends_at->format('Y-m-d H:i:s'), $timezone);

                return [
                    'shift_id' => $other->shift_id, 'shift_name' => $other->shift->name,
                    'starts_at' => $other->starts_at->format('Y-m-d\TH:i'),
                    'ends_at' => $other->ends_at->format('Y-m-d\TH:i'),
                    'overlap_minutes' => (int) $start->max($otherStart)->diffInMinutes($end->min($otherEnd)),
                ];
            })->values()->all();
    }
}
