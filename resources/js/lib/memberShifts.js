import { scheduleDateLabel } from './scheduleTimeline.js';

export function memberShiftTimeLabel(assignment, locale, translate) {
    const startDay = assignment.starts_at.slice(0, 10);
    const endDay = assignment.ends_at.slice(0, 10);
    return translate(
        startDay === endDay
            ? 'team.member.shifts.time_range'
            : 'team.member.shifts.overnight_time_range',
        {
            start: assignment.starts_at.slice(11, 16),
            end: assignment.ends_at.slice(11, 16),
            day: scheduleDateLabel(endDay, locale),
        },
    );
}
