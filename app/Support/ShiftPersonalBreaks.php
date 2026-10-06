<?php

namespace App\Support;

use App\Models\ShiftAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ShiftPersonalBreaks
{
    public static function fits(array $break, string $start, string $end): bool
    {
        $time = CarbonImmutable::parse($break['starts_at']);

        return $time->gte(CarbonImmutable::parse($start)) && $time->addMinutes((int) $break['duration_minutes'])->lte(CarbonImmutable::parse($end));
    }

    public static function overlaps(array $break, array $rows): bool
    {
        $start = CarbonImmutable::parse($break['starts_at']);
        $end = $start->addMinutes((int) $break['duration_minutes']);
        foreach ($rows as $row) {
            if (empty($row['starts_at']) || ! CarbonImmutable::hasFormatWithModifiers($row['starts_at'], 'Y-m-d\TH:i')) {
                continue;
            }
            $other = CarbonImmutable::parse($row['starts_at']);
            if ($start->lt($other->addMinutes((int) $row['duration_minutes'])) && $other->lt($end)) {
                return true;
            }
        }

        return false;
    }

    public static function snapshot(Collection $defaults, string $start, string $end): array
    {
        return $defaults->filter(fn ($break) => self::fits($break->toArray(), $start, $end))->map(fn ($break) => [
            'shift_break_id' => $break->id, 'duration_minutes' => $break->duration_minutes,
            'starts_at' => $break->starts_at->format('Y-m-d\TH:i'),
        ])->values()->all();
    }

    public static function errors(array $rows, string $start, string $end, Collection $existing, Collection $sources, array $draftKeys = []): array
    {
        $errors = [];
        foreach ($rows as $i => $row) {
            if (! in_array($row['duration_minutes'] ?? null, ShiftBreaks::DURATIONS, true)) {
                $errors["breaks.$i.duration_minutes"] = __('team.scheduling.breaks.errors.duration');
            }
            if (empty($row['starts_at']) || ! CarbonImmutable::hasFormatWithModifiers($row['starts_at'], 'Y-m-d\TH:i')) {
                $errors["breaks.$i.starts_at"] = __('team.scheduling.breaks.errors.start');
            }
            if (isset($row['shift_break_id']) && ! $sources->has((int) $row['shift_break_id'])) {
                $errors["breaks.$i.shift_break_id"] = __('team.scheduling.breaks.errors.foreign');
            }
            if (isset($row['shift_break_key']) && ! in_array($row['shift_break_key'], $draftKeys, true)) {
                $errors["breaks.$i.shift_break_key"] = __('team.scheduling.breaks.errors.foreign');
            }
        }
        if ($errors !== []) {
            return $errors;
        }

        return array_map(fn ($message) => $message === __('team.scheduling.breaks.errors.containment') ? __('team.scheduling.breaks.errors.personal_containment') : $message, ShiftBreaks::errors(['starts_at' => $start, 'ends_at' => $end, 'breaks' => $rows], $existing));
    }

    public static function sync(ShiftAssignment $assignment, array $rows, array $draftSources = []): void
    {
        $existing = $assignment->breaks()->lockForUpdate()->get()->keyBy('id');
        $sources = $assignment->shift->breaks()->get()->keyBy('id');
        foreach ($rows as $index => &$row) {
            if (isset($row['shift_break_key'])) {
                if (! isset($draftSources[$row['shift_break_key']])) {
                    throw ValidationException::withMessages(["breaks.$index.shift_break_key" => __('team.scheduling.breaks.errors.foreign')]);
                }
                $row['shift_break_id'] = $draftSources[$row['shift_break_key']] ?? null;
                unset($row['shift_break_key']);
            }
        }
        unset($row);
        $errors = self::errors($rows, $assignment->starts_at->format('Y-m-d\TH:i'), $assignment->ends_at->format('Y-m-d\TH:i'), $existing, $sources);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $kept = [];
        foreach ($rows as $index => $row) {
            $break = isset($row['id']) ? $existing->get($row['id']) : null;
            unset($row['id']);
            $row['sort_order'] = $index;
            if ($break) {
                $break->update($row);
            } else {
                $break = $assignment->breaks()->create($row);
            }
            $kept[] = $break->id;
        }
        $assignment->breaks()->whereNotIn('id', $kept)->delete();
    }
}
