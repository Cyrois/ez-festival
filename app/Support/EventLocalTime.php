<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

final class EventLocalTime
{
    /** An event-local minute must map to exactly one instant. */
    public static function instant(string $value, string $timezone): ?CarbonImmutable
    {
        $wall = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('UTC'));
        if ($wall === false || $wall->format('Y-m-d\TH:i') !== $value) {
            return null;
        }
        $zone = new DateTimeZone($timezone);
        $stamp = $wall->getTimestamp();
        $transitions = $zone->getTransitions($stamp - 172800, $stamp + 172800);
        $offsets = $transitions === false ? [$zone->getOffset($wall)] : array_unique(array_column($transitions, 'offset'));
        $matches = [];
        foreach ($offsets as $offset) {
            $candidate = CarbonImmutable::createFromTimestampUTC($stamp - $offset)->setTimezone($zone);
            if ($candidate->format('Y-m-d\TH:i') === $value) {
                $matches[] = $candidate;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    public static function validate(array $data, string $timezone): void
    {
        $errors = [];
        foreach (['starts_at', 'ends_at'] as $key) {
            if (isset($data[$key]) && self::instant($data[$key], $timezone) === null) {
                $errors[$key] = __('team.scheduling.errors.local_time');
            }
        }
        foreach ($data['breaks'] ?? [] as $index => $row) {
            if (self::instant($row['starts_at'], $timezone) === null) {
                $errors["breaks.$index.starts_at"] = __('team.scheduling.errors.local_time');
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function elapsedMinutes($start, $end, string $timezone): int
    {
        // Legacy rows have no offset. New writes reject ambiguous/nonexistent values;
        // existing rows retain Carbon's interpretation until explicitly corrected.
        $start = CarbonImmutable::parse($start->format('Y-m-d H:i:s'), $timezone);
        $end = CarbonImmutable::parse($end->format('Y-m-d H:i:s'), $timezone);

        return (int) $start->diffInMinutes($end);
    }
}
