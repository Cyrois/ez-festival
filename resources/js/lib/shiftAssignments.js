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

// Durations come from the server (including event-timezone DST handling).
export function assignmentDurationLabel(minutes, translate) {
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    return hours
        ? translate(
              remainder
                  ? 'team.scheduling.assignments.duration_hours_minutes'
                  : 'team.scheduling.assignments.duration_hours',
              { hours, minutes: remainder },
          )
        : translate('team.scheduling.assignments.duration_minutes', {
              minutes,
          });
}

export function assignmentOverlapDetails(overlaps, hours, translate) {
    return overlaps
        .map((overlap) => {
            const start =
                hours.starts_at > overlap.starts_at
                    ? hours.starts_at
                    : overlap.starts_at;
            const end =
                hours.ends_at < overlap.ends_at
                    ? hours.ends_at
                    : overlap.ends_at;
            return [
                translate('team.scheduling.assignments.also_on', {
                    name: overlap.shift_name,
                }),
                `${overlap.starts_at.slice(0, 10)} ${overlap.starts_at.slice(11, 16)}–${overlap.ends_at.slice(11, 16)}`,
                translate('team.scheduling.assignments.overlap_details', {
                    from: start.slice(11, 16),
                    to: end.slice(11, 16),
                    length: assignmentDurationLabel(
                        overlap.overlap_minutes,
                        translate,
                    ),
                }),
            ].join('\n');
        })
        .join('\n\n');
}
