<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Repositories\ShiftBreakRepository;
use App\Support\ShiftAssignmentHours;
use App\Support\ShiftBreaks;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Replay draft edits in order so bulk conflicts are checked against the current personal breaks. */
final class ShiftBreakService
{
    public function __construct(private ShiftBreakRepository $repository) {}

    public function replay(array $data, Collection $sources, Collection $assignments): void
    {
        if (empty($data['break_operations'])) {
            return;
        }
        $defaults = $sources->mapWithKeys(fn ($row) => [(string) $row->id => $this->value($row->toArray())])->all();
        $people = $assignments->mapWithKeys(fn ($row) => [$row->id => [
            'starts_at' => $row->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $row->ends_at->format('Y-m-d\TH:i'),
            'breaks' => $row->breaks->map(fn ($break) => $this->value($break->toArray(), personal: true))->all(),
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
                    $this->invalid($index);
                }
                $people[$key] = ['starts_at' => $op['starts_at'], 'ends_at' => $op['ends_at'],
                    'breaks' => array_map(fn ($row) => $this->value($row, personal: true), $op['breaks'])];

                continue;
            }
            if ($op['type'] === 'mass') {
                $after = $this->value($op['break']);
                $keys = $op['assignment_keys'];
                if (count(array_unique($keys)) !== count($keys)
                    || ! $this->fits($after, $data['starts_at'], $data['ends_at'])) {
                    $this->invalid($index);
                }
                // Check every target before changing anyone, including people whose hours are shorter.
                foreach ($keys as $key) {
                    if (! isset($people[$key])) {
                        $this->invalid($index);
                    }
                    if ($this->overlaps($after, $people[$key]['breaks'])) {
                        throw ValidationException::withMessages([
                            "break_operations.$index" => __('team.scheduling.breaks.errors.mass_conflict'),
                        ]);
                    }
                }
                foreach ($keys as $key) {
                    if ($this->fits($after, $people[$key]['starts_at'], $people[$key]['ends_at'])) {
                        $people[$key]['breaks'][] = [...$after, 'shift_break_id' => null];
                    }
                }

                continue;
            }
            $key = (string) $op['source'];
            $before = $defaults[$key] ?? null;
            $after = isset($op['break']) ? $this->value($op['break']) : null;
            if ($before === null && ($after === null || ! preg_match('/^draft-break-\d+$/', $key))) {
                $this->invalid($index);
            }
            foreach ($people as &$person) {
                $found = null;
                foreach ($person['breaks'] as $i => $row) {
                    $source = $row['shift_break_key'] ?? $row['shift_break_id'] ?? null;
                    if ((string) $source === $key && $before !== null && $this->sameTime($row, $before)) {
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
                    } elseif ($this->fits($after, $person['starts_at'], $person['ends_at']) && ! $this->overlaps($after, $others)) {
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
            $finalDefaults[(string) ($row['id'] ?? $row['client_key'] ?? '')] = $this->value($row);
        }
        ksort($defaults);
        ksort($finalDefaults);
        if ($defaults !== $finalDefaults) {
            $this->invalid();
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
            if ($this->normalizedPersonal($rows) !== $this->normalizedPersonal($person['breaks'])) {
                $this->invalid();
            }
        }
    }

    /** Break display order is not part of the conflict contract; identity, source and time are. */
    private function normalizedPersonal(array $rows): array
    {
        $values = array_map(fn ($row) => $this->value($row, personal: true), $rows);
        usort($values, fn ($a, $b) => $a <=> $b);

        return $values;
    }

    private function value(array $row, bool $personal = false): array
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

    private function sameTime(array $a, array $b): bool
    {
        return $a['starts_at'] === $b['starts_at'] && $a['duration_minutes'] === $b['duration_minutes'];
    }

    private function invalid(?int $index = null): never
    {
        throw ValidationException::withMessages([$index === null ? 'break_operations' : "break_operations.$index" => __('team.scheduling.breaks.errors.stale_draft')]);
    }

    public function fits(array $break, string $start, string $end): bool
    {
        $time = CarbonImmutable::parse($break['starts_at']);

        return $time->gte(CarbonImmutable::parse($start)) && $time->addMinutes((int) $break['duration_minutes'])->lte(CarbonImmutable::parse($end));
    }

    public function overlaps(array $break, array $rows): bool
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

    public function snapshot(Collection $defaults, string $start, string $end): array
    {
        return $defaults->filter(fn ($break) => $this->fits($break->toArray(), $start, $end))->map(fn ($break) => [
            'shift_break_id' => $break->id, 'duration_minutes' => $break->duration_minutes,
            'starts_at' => $break->starts_at->format('Y-m-d\TH:i'),
        ])->values()->all();
    }

    public function errors(array $rows, string $start, string $end, Collection $existing, Collection $sources, array $draftKeys = []): array
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

    public function sync(ShiftAssignment $assignment, array $rows, array $draftSources = []): void
    {
        $existing = $this->repository->personal($assignment);
        $sources = $this->repository->defaults($assignment->shift);
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
        $errors = $this->errors($rows, $assignment->starts_at->format('Y-m-d\TH:i'), $assignment->ends_at->format('Y-m-d\TH:i'), $existing, $sources);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $this->repository->syncPersonal($assignment, $rows, $existing);
    }
}
