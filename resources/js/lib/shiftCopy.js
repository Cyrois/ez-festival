import { newShiftSlot } from './shiftRoleSlots.js';
import { newShiftBreak, wallMinutes } from './shiftBreaks.js';

import { personalBreakDraft, movePersonalBreaks } from './personalBreaks.js';

// Only snapshot values enter the new shift's draft; no source identifier survives.
export function copiedShiftDraft(prefill) {
    const slots = (prefill.slots ?? []).map((slot) => ({
        ...newShiftSlot(),
        ...slot,
    }));
    const breaks = (prefill.breaks ?? []).map((row) =>
        newShiftBreak(
            row.duration_minutes,
            row.starts_at.slice(0, 10),
            row.starts_at.slice(11, 16),
        ),
    );
    const people = {};
    const additions = (prefill.assignments ?? []).map((person, index) => {
        const key = -index - 1;
        const slot = slots[person.slot_index];
        people[key] = {
            ...person,
            overlaps: [],
            other_shifts: [],
            shift_role_slot_id: slot?._key ?? null,
            is_extra: !slot,
        };
        return {
            _key: key,
            breaks: personalBreakDraft(
                (person.breaks ?? []).map((row) => ({
                    duration_minutes: row.duration_minutes,
                    starts_at: row.starts_at,
                    ...(row.source_break_index != null &&
                    breaks[row.source_break_index]
                        ? {
                              shift_break_key:
                                  breaks[row.source_break_index]._key,
                          }
                        : { shift_break_id: null }),
                })),
            ),
            team_engagement_id: person.team_engagement_id,
            ...(slot
                ? { slot_key: slot._key }
                : person.role_id == null
                  ? { extra: true }
                  : { role_id: person.role_id }),
            hours_mode: person.hours_mode,
            ...(person.hours_mode === 'custom'
                ? { starts_at: person.starts_at, ends_at: person.ends_at }
                : {}),
        };
    });
    return {
        slots,
        breaks,
        assignment_additions: additions,
        supervisor_key:
            additions.find((row) => people[row._key].is_supervisor)?._key ??
            null,
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
        assignment_additions: draft.assignment_additions.map((row) => ({
            ...row,
            ...(row.hours_mode === 'custom'
                ? {
                      starts_at: moved(row.starts_at, delta),
                      ends_at: moved(row.ends_at, delta),
                  }
                : {}),
            breaks: movePersonalBreaks(row.breaks ?? [], delta),
        })),
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
