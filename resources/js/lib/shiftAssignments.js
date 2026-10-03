export function assignmentPayload(slotId, memberId, mode, start, end) {
    return {
        shift_role_slot_id: slotId,
        team_engagement_id: memberId,
        hours_mode: mode,
        ...(mode === 'custom' ? { starts_at: start, ends_at: end } : {}),
    };
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
