<?php

namespace App\Support;

use App\Models\Shift;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Replay draft edits in order so bulk conflicts are checked against the current personal breaks. */
final class ShiftBreakOperations
{
    public static function replay(Shift $shift, array $data, Collection $sources, Collection $assignments): void
    {
        if (empty($data['break_operations'])) {
            return;
        }
        $defaults = $sources->mapWithKeys(fn ($row) => [(string) $row->id => self::value($row->toArray())])->all();
        $people = $assignments->mapWithKeys(fn ($row) => [$row->id => [
            'starts_at' => $row->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $row->ends_at->format('Y-m-d\TH:i'),
            'breaks' => $row->breaks->map(fn ($break) => self::value($break->toArray(), personal: true))->all(),
        ]])->all();
        foreach ($data['assignment_additions'] ?? [] as $row) {
            if (isset($row['client_key'])) {
                [$start, $end] = ShiftAssignmentHours::resolve(new Shift($data), $row);
                $people[$row['client_key']] = ['starts_at' => $start, 'ends_at' => $end, 'breaks' => []];
            }
        }
        foreach ($data['break_operations'] as $index => $op) {
            if ($op['type'] === 'person') {
                $key = $op['assignment_key'];
                if (! isset($people[$key])) {
                    self::invalid($index);
                }
                $people[$key] = ['starts_at' => $op['starts_at'], 'ends_at' => $op['ends_at'],
                    'breaks' => array_map(fn ($row) => self::value($row, personal: true), $op['breaks'])];

                continue;
            }
            if ($op['type'] === 'mass') {
                $after = self::value($op['break']);
                $keys = $op['assignment_keys'];
                if (count(array_unique($keys)) !== count($keys)
                    || ! ShiftPersonalBreaks::fits($after, $data['starts_at'], $data['ends_at'])) {
                    self::invalid($index);
                }
                // Check every target before changing anyone, including people whose hours are shorter.
                foreach ($keys as $key) {
                    if (! isset($people[$key])) {
                        self::invalid($index);
                    }
                    if (ShiftPersonalBreaks::overlaps($after, $people[$key]['breaks'])) {
                        throw ValidationException::withMessages([
                            "break_operations.$index" => __('team.scheduling.breaks.errors.mass_conflict'),
                        ]);
                    }
                }
                foreach ($keys as $key) {
                    if (ShiftPersonalBreaks::fits($after, $people[$key]['starts_at'], $people[$key]['ends_at'])) {
                        $people[$key]['breaks'][] = [...$after, 'shift_break_id' => null];
                    }
                }

                continue;
            }
            $key = (string) $op['source'];
            $before = $defaults[$key] ?? null;
            $after = isset($op['break']) ? self::value($op['break']) : null;
            if ($before === null && ($after === null || ! preg_match('/^draft-break-\d+$/', $key))) {
                self::invalid($index);
            }
            foreach ($people as &$person) {
                $found = null;
                foreach ($person['breaks'] as $i => $row) {
                    $source = $row['shift_break_key'] ?? $row['shift_break_id'] ?? null;
                    if ((string) $source === $key && $before !== null && self::sameTime($row, $before)) {
                        $found = $i;
                        break;
                    }
                }
                if ($op['apply'] && ($before === null || $found !== null)) {
                    $others = $person['breaks'];
                    if ($found !== null) {
                        array_splice($others, $found, 1);
                    }
                    if ($after === null) {
                        $person['breaks'] = $others;
                    } elseif (ShiftPersonalBreaks::fits($after, $person['starts_at'], $person['ends_at']) && ! ShiftPersonalBreaks::overlaps($after, $others)) {
                        $copy = [...$after, ...(is_numeric($key) ? ['shift_break_id' => (int) $key] : ['shift_break_key' => $key])];
                        if ($found !== null) {
                            $person['breaks'][$found] = [...$person['breaks'][$found], ...$copy];
                        } else {
                            $person['breaks'][] = $copy;
                        }
                    }
                }
                if ($after === null) {
                    foreach ($person['breaks'] as &$row) {
                        if ((string) ($row['shift_break_key'] ?? $row['shift_break_id'] ?? '') === $key) {
                            unset($row['shift_break_key']);
                            $row['shift_break_id'] = null;
                        }
                    }
                    unset($row);
                }
            }
            unset($person);
            if ($after === null) {
                unset($defaults[$key]);
            } else {
                $defaults[$key] = $after;
            }
        }
        $finalDefaults = [];
        foreach ($data['breaks'] ?? [] as $row) {
            $finalDefaults[(string) ($row['id'] ?? $row['client_key'] ?? '')] = self::value($row);
        }
        ksort($defaults);
        ksort($finalDefaults);
        if ($defaults !== $finalDefaults) {
            self::invalid();
        }
        $submitted = [];
        foreach ($data['assignment_updates'] ?? [] as $row) {
            if (array_key_exists('breaks', $row)) {
                $submitted[$row['id']] = $row['breaks'];
            }
        }
        foreach ($data['assignment_additions'] ?? [] as $row) {
            if (isset($row['client_key']) && array_key_exists('breaks', $row)) {
                $submitted[$row['client_key']] = $row['breaks'];
            }
        }
        foreach ($people as $key => $person) {
            if (in_array($key, $data['assignment_removals'] ?? [], true)) {
                continue;
            }
            $rows = $submitted[$key] ?? ($assignments->get($key)?->breaks->map(fn ($row) => $row->toArray())->all() ?? []);
            if (array_map(fn ($row) => self::value($row, personal: true), $rows) !== array_map(fn ($row) => self::value($row, personal: true), $person['breaks'])) {
                self::invalid();
            }
        }
    }

    private static function value(array $row, bool $personal = false): array
    {
        $start = $row['starts_at'] ?? '';
        // Intermediate drafts may have incomplete dates. Saved ISO timestamps still normalize to wall minutes.
        try {
            $start = $start === '' ? '' : CarbonImmutable::parse($start)->format('Y-m-d\TH:i');
        } catch (InvalidFormatException) {
            // Retain invalid intermediate values; final personal break validation refuses them on Save.
        }
        $result = ['duration_minutes' => (int) $row['duration_minutes'], 'starts_at' => $start];
        if ($personal) {
            $result = [...(isset($row['id']) ? ['id' => (int) $row['id']] : []), ...$result,
                'shift_break_id' => isset($row['shift_break_id']) ? (int) $row['shift_break_id'] : null,
                ...(isset($row['shift_break_key']) ? ['shift_break_key' => $row['shift_break_key']] : [])];
        }

        return $result;
    }

    private static function sameTime(array $a, array $b): bool
    {
        return $a['starts_at'] === $b['starts_at'] && $a['duration_minutes'] === $b['duration_minutes'];
    }

    private static function invalid(?int $index = null): never
    {
        throw ValidationException::withMessages([$index === null ? 'break_operations' : "break_operations.$index" => __('team.scheduling.breaks.errors.stale_draft')]);
    }
}
