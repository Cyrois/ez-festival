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
