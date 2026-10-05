export function assignmentPayload(slotId, memberId, mode, start, end) {
    return {
        shift_role_slot_id: slotId,
        team_engagement_id: memberId,
        hours_mode: mode,
        ...(mode === 'custom' ? { starts_at: start, ends_at: end } : {}),
    };
}

export function assignmentCandidatesUrl(shift, eventId) {
    return shift.id
        ? `/team/shifts/${shift.id}/assignment-candidates`
        : `/team/events/${eventId}/shifts/assignment-candidates`;
}

export function assignmentOverlapUrl(shift, eventId, assignment) {
    if (!shift.id) return `/team/events/${eventId}/shifts/assignment-overlaps`;
    return `/team/shifts/${shift.id}/${assignment.id > 0 ? `assignments/${assignment.id}/overlaps` : 'assignment-overlaps'}`;
}

export function validAssignmentHours(shift, mode, start, end) {
    return (
        mode === 'full_shift' ||
        Boolean(
            start &&
            end &&
            shift.starts_at <= start &&
            start < end &&
            end <= shift.ends_at,
        )
    );
}

export function requirementRoster(shift, slot) {
    return shift.assignments.filter(
        (assignment) =>
            assignment.shift_role_slot_id === slot.id &&
            assignment.role_id === slot.role_id,
    );
}

export function scheduleRosterRows(shift) {
    const rows = shift.slots.flatMap((slot) => [
        ...requirementRoster(shift, slot).map((assignment) => ({
            key: `person-${assignment.id}`,
            assignment,
        })),
        ...Array.from({ length: slot.open_count }, (_, index) => ({
            key: `open-${slot.id}-${index}`,
            slot,
        })),
    ]);
    return [
        ...rows,
        ...shift.assignments
            .filter((assignment) => assignment.shift_role_slot_id === null)
            .map((assignment) => ({
                key: `person-${assignment.id}`,
                assignment,
            })),
    ];
}

export function draftRoster(
    shift,
    updates = [],
    removals = [],
    additions = [],
) {
    const assignments = shift.assignments
        .filter((row) => !removals.includes(row.id))
        .map((row) => {
            const update = updates.find((change) => change.id === row.id);
            return {
                ...row,
                ...(update
                    ? {
                          starts_at:
                              update.hours_mode === 'full_shift'
                                  ? shift.starts_at
                                  : update.starts_at,
                          ends_at:
                              update.hours_mode === 'full_shift'
                                  ? shift.ends_at
                                  : update.ends_at,
                      }
                    : {}),
            };
        })
        .concat(additions.map((row) => ({ ...row })));
    let filled = 0;
    const slots = shift.slots.map((slot) => {
        const members = assignments.filter(
            (row) =>
                row.shift_role_slot_id === slot.id &&
                row.role_id === slot.role_id,
        );
        filled += Math.min(members.length, slot.needed);
        members.forEach((row, index) => (row.is_extra = index >= slot.needed));
        return {
            ...slot,
            assigned_count: members.length,
            open_count: Math.max(0, slot.needed - members.length),
        };
    });
    return {
        ...shift,
        slots,
        assignments,
        filled_count: filled,
        assignment_count: assignments.length,
        extra_count: assignments.length - filled,
    };
}

export function translatedAssignment(shift, assignment, minute) {
    const origin = Date.parse(shift.starts_at + 'Z') / 60000;
    const end = Date.parse(shift.ends_at + 'Z') / 60000;
    const length =
        (Date.parse(assignment.ends_at + 'Z') -
            Date.parse(assignment.starts_at + 'Z')) /
        60000;
    const earliest = Math.ceil(origin / 15) * 15;
    const latest = Math.floor((end - length) / 15) * 15;
    if (length <= 0 || earliest > latest) return null;
    const start = Math.max(
        earliest,
        Math.min(latest, Math.round(minute / 15) * 15),
    );
    return {
        starts_at: new Date(start * 60000).toISOString().slice(0, 16),
        ends_at: new Date((start + length) * 60000).toISOString().slice(0, 16),
    };
}

export function resizedAssignment(shift, assignment, edge, minute) {
    const origin = Date.parse(shift.starts_at + 'Z') / 60000;
    const end = Date.parse(shift.ends_at + 'Z') / 60000;
    const currentStart = Date.parse(assignment.starts_at + 'Z') / 60000;
    const currentEnd = Date.parse(assignment.ends_at + 'Z') / 60000;
    const snapped = Math.round(minute / 15) * 15;
    // Never move the opposite edge; keep at least one minute if it is off-grid.
    const value =
        edge === 'start'
            ? Math.max(
                  Math.ceil(origin / 15) * 15,
                  Math.min(Math.floor((currentEnd - 1) / 15) * 15, snapped),
              )
            : Math.min(
                  Math.floor(end / 15) * 15,
                  Math.max(Math.ceil((currentStart + 1) / 15) * 15, snapped),
              );
    if (
        edge === 'start'
            ? value >= currentEnd || value < origin
            : value <= currentStart || value > end
    )
        return null;
    const stamp = new Date(value * 60000).toISOString().slice(0, 16);
    return {
        starts_at: edge === 'start' ? stamp : assignment.starts_at,
        ends_at: edge === 'end' ? stamp : assignment.ends_at,
    };
}
