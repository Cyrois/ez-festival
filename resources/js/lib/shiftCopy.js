import { newShiftSlot, totalShiftNeeds } from './shiftRoleSlots.js';
import { newShiftBreak, wallMinutes } from './shiftBreaks.js';

export function copiedShiftDraft(prefill) {
    const slots = (prefill.slots ?? []).map((slot) => ({
        ...newShiftSlot(),
        ...slot,
    }));
    return {
        slots,
        breaks: (prefill.breaks ?? []).map((row) => ({
            ...newShiftBreak(
                row.duration_minutes,
                row.starts_at.slice(0, 10),
                row.starts_at.slice(11, 16),
            ),
        })),
        assignments: (prefill.assignments ?? []).map((row, index) => ({
            ...row,
            _key: `copy-person-${index}`,
            _slot_key: slots[row.slot_index]?._key ?? '',
        })),
    };
}

const moved = (value, minutes) => {
    const time = wallMinutes(value);
    return Number.isFinite(time)
        ? new Date((time + minutes) * 60000).toISOString().slice(0, 16)
        : value;
};

export function moveCopiedShift(draft, previousStart) {
    const delta = wallMinutes(draft.starts_at) - wallMinutes(previousStart);
    return {
        assignments: draft.assignments.map((row) => ({
            ...row,
            starts_at:
                row.hours_mode === 'full_shift'
                    ? draft.starts_at
                    : Number.isFinite(delta)
                      ? moved(row.starts_at, delta)
                      : row.starts_at,
            ends_at:
                row.hours_mode === 'full_shift'
                    ? draft.ends_at
                    : Number.isFinite(delta)
                      ? moved(row.ends_at, delta)
                      : row.ends_at,
        })),
        breaks: draft.breaks.map((row) => {
            const starts_at = Number.isFinite(delta)
                ? moved(row.starts_at, delta)
                : row.starts_at;
            return {
                ...row,
                starts_at,
                _day: starts_at.slice(0, 10),
                _time: starts_at.slice(11, 16),
            };
        }),
    };
}

export function copiedAssignmentPayload(assignments, slots) {
    return assignments.map((row) => {
        const index = slots.findIndex((slot) => slot._key === row._slot_key);
        return {
            team_engagement_id: row.team_engagement_id,
            slot_index: index < 0 ? null : index,
            hours_mode: row.hours_mode,
            ...(row.hours_mode === 'custom'
                ? { starts_at: row.starts_at, ends_at: row.ends_at }
                : {}),
        };
    });
}

export function copiedAssignmentErrors(assignments, errors) {
    const result = {};
    for (const [path, message] of Object.entries(errors)) {
        const match = /^assignments\.(\d+)(?:\.(\w+))?$/.exec(path);
        const key = match && assignments[Number(match[1])]?._key;
        if (key) {
            result[key] ??= {};
            result[key][match[2] ?? 'row'] = Array.isArray(message)
                ? message[0]
                : message;
        }
    }
    return result;
}

export function copiedRoster(draft) {
    const counts = new Map();
    const slots = new Map(draft.slots.map((slot) => [slot._key, slot]));
    const assignments = draft.assignments.map((row) => {
        const slot = slots.get(row._slot_key);
        const count = (counts.get(row._slot_key) ?? 0) + 1;
        counts.set(row._slot_key, count);
        return {
            ...row,
            role_name: slot?.role_name ?? row.role_name,
            is_extra: !slot || count > Number(slot.needed),
        };
    });
    const filled = draft.slots.reduce(
        (sum, slot) =>
            sum +
            Math.min(counts.get(slot._key) ?? 0, Number(slot.needed) || 0),
        0,
    );
    return {
        assignments,
        total_needs: totalShiftNeeds(draft.slots),
        filled_count: filled,
        extra_count: assignments.length - filled,
    };
}

export function copiedHoursValid(row, start, end) {
    const first = wallMinutes(start);
    const last = wallMinutes(end);
    const from = wallMinutes(
        row.hours_mode === 'full_shift' ? start : row.starts_at,
    );
    const to = wallMinutes(row.hours_mode === 'full_shift' ? end : row.ends_at);
    return (
        [first, last, from, to].every(Number.isFinite) &&
        first <= from &&
        from < to &&
        to <= last
    );
}
