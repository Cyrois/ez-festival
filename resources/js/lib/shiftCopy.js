import { newShiftSlot } from './shiftRoleSlots.js';
import { newShiftBreak, wallMinutes } from './shiftBreaks.js';

// Only snapshot values enter the new shift's draft; no source identifier survives.
export function copiedShiftDraft(prefill) {
    const slots = (prefill.slots ?? []).map((slot) => ({
        ...newShiftSlot(),
        ...slot,
    }));
    const people = {};
    const additions = (prefill.assignments ?? []).map((person, index) => {
        const key = -index - 1;
        const slot = slots[person.slot_index];
        people[key] = {
            ...person,
            shift_role_slot_id: slot?._key ?? null,
            is_extra: !slot,
        };
        return {
            _key: key,
            team_engagement_id: person.team_engagement_id,
            ...(slot ? { slot_key: slot._key } : { role_id: person.role_id }),
            hours_mode: person.hours_mode,
            ...(person.hours_mode === 'custom'
                ? { starts_at: person.starts_at, ends_at: person.ends_at }
                : {}),
        };
    });
    return {
        slots,
        breaks: (prefill.breaks ?? []).map((row) =>
            newShiftBreak(
                row.duration_minutes,
                row.starts_at.slice(0, 10),
                row.starts_at.slice(11, 16),
            ),
        ),
        assignment_additions: additions,
        people,
    };
}
const moved = (value, delta) => {
    const time = wallMinutes(value);
    return Number.isFinite(time)
        ? new Date((time + delta) * 60000).toISOString().slice(0, 16)
        : value;
};
export function moveCopiedShift(draft, previousStart) {
    const delta = wallMinutes(draft.starts_at) - wallMinutes(previousStart);
    if (!Number.isFinite(delta))
        return {
            assignment_additions: draft.assignment_additions,
            breaks: draft.breaks,
        };
    return {
        assignment_additions: draft.assignment_additions.map((row) =>
            row.hours_mode === 'full_shift'
                ? row
                : {
                      ...row,
                      starts_at: moved(row.starts_at, delta),
                      ends_at: moved(row.ends_at, delta),
                  },
        ),
        breaks: draft.breaks.map((row) => {
            const starts_at = moved(row.starts_at, delta);
            return {
                ...row,
                starts_at,
                _day: starts_at.slice(0, 10),
                _time: starts_at.slice(11, 16),
            };
        }),
    };
}
