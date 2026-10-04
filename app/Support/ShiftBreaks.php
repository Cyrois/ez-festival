<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class ShiftBreaks
{
    public const DURATIONS = [15, 30, 45, 60];

    public const DEFAULT_DURATION = 15;

    public static function options(): array
    {
        return ['durations' => self::DURATIONS, 'default_duration' => self::DEFAULT_DURATION];
    }

    /**
     * Validate the resulting collection against proposed bounds, also inside the write transaction.
     * Omitted breaks are retained and must still fit if the shift's times change.
     *
     * @return array<string, string>
     */
    public static function errors(array $data, Collection $existing, string $timezone = 'UTC'): array
    {
        $supplied = array_key_exists('breaks', $data);
        $rows = $supplied ? $data['breaks'] : $existing->map->only(['id', 'duration_minutes', 'starts_at'])->values()->all();
        $shiftStart = CarbonImmutable::parse($data['starts_at'], $timezone);
        $shiftEnd = CarbonImmutable::parse($data['ends_at'], $timezone);
        $errors = [];
        $intervals = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            if (isset($row['id'])) {
                $id = (int) $row['id'];
                if (! $existing->has($id) || isset($seen[$id])) {
                    $errors["breaks.$index.id"] = __('team.scheduling.breaks.errors.foreign');
                }
                $seen[$id] = true;
            }

            $start = CarbonImmutable::parse(is_object($row['starts_at']) ? $row['starts_at']->format('Y-m-d H:i:s') : $row['starts_at'], $timezone);
            $end = $start->utc()->addMinutes((int) $row['duration_minutes'])->setTimezone($timezone);
            if ($start->lt($shiftStart) || $end->gt($shiftEnd)) {
                $key = $supplied ? "breaks.$index.starts_at" : ($start->lt($shiftStart) ? 'starts_at' : 'ends_at');
                $errors[$key] = $supplied
                    ? __('team.scheduling.breaks.errors.containment')
                    : __('team.scheduling.breaks.errors.retained');
            }
            $intervals[] = ['index' => $index, 'start' => $start, 'end' => $end];
        }

        usort($intervals, fn ($a, $b) => $a['start'] <=> $b['start']);
        $latestEnd = null;
        foreach ($intervals as $interval) {
            if ($latestEnd !== null && $interval['start']->lt($latestEnd)) {
                $errors["breaks.{$interval['index']}.starts_at"] = __('team.scheduling.breaks.errors.overlap');
            }
            $latestEnd = $latestEnd === null ? $interval['end'] : $latestEnd->max($interval['end']);
        }

        return $errors;
    }
}
