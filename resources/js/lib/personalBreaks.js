import { newShiftBreak, wallMinutes } from './shiftBreaks.js';

export function personalBreakPayload(rows) {
    return rows.map((row) => ({
        ...(row.id != null ? { id: row.id } : {}),
        duration_minutes: Number(row.duration_minutes),
        starts_at: row.starts_at,
        ...(row.shift_break_key
            ? { shift_break_key: row.shift_break_key }
            : { shift_break_id: row.shift_break_id ?? null }),
    }));
}
export function personalBreakDraft(rows = []) {
    return rows.map((row) => ({
        ...newShiftBreak(row.duration_minutes),
        ...row,
        ...(row.id != null ? { _key: `saved-personal-break-${row.id}` } : {}),
        _day: row.starts_at ? row.starts_at.slice(0, 10) : (row._day ?? ''),
        _time: row.starts_at ? row.starts_at.slice(11, 16) : (row._time ?? ''),
    }));
}
export function defaultSource(row) {
    return String(row.id ?? row._key);
}
export function fittingBreak(row, person) {
    const start = wallMinutes(row.starts_at);
    return (
        start >= wallMinutes(person.starts_at) &&
        start + Number(row.duration_minutes) <= wallMinutes(person.ends_at)
    );
}
export function overlappingBreak(row, others) {
    const start = wallMinutes(row.starts_at);
    return others.some(
        (other) =>
            start <
                wallMinutes(other.starts_at) + Number(other.duration_minutes) &&
            wallMinutes(other.starts_at) < start + Number(row.duration_minutes),
    );
}
export function movePersonalBreaks(rows = [], delta = 0) {
    return personalBreakDraft(
        rows.map((row) => {
            const time = wallMinutes(row.starts_at);
            return {
                ...row,
                starts_at: Number.isFinite(time)
                    ? new Date((time + delta) * 60000)
                          .toISOString()
                          .slice(0, 16)
                    : row.starts_at,
            };
        }),
    );
}
export function massPersonalBreaks(people, row) {
    if (people.some((person) => overlappingBreak(row, person.breaks ?? [])))
        return { conflict: true, people: [], skipped: 0 };
    const fitting = people.filter((person) => fittingBreak(row, person));
    return {
        conflict: false,
        skipped: people.length - fitting.length,
        people: fitting.map((person) => ({
            ...person,
            breaks: [
                ...(person.breaks ?? []),
                ...personalBreakDraft([
                    {
                        duration_minutes: Number(row.duration_minutes),
                        starts_at: row.starts_at,
                        shift_break_id: null,
                    },
                ]),
            ],
        })),
    };
}
