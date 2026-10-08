// A meal date is an event-local calendar day, independent of the device timezone.
export function mealDateLabel(date, locale, options = {}) {
    if (!date) return '';
    return new Intl.DateTimeFormat(locale, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        ...options,
        timeZone: 'UTC',
    }).format(new Date(`${date}T12:00:00Z`));
}

export function mealNextDayLabel(date, locale) {
    const day = new Date(`${date}T12:00:00Z`);
    day.setUTCDate(day.getUTCDate() + 1);
    return new Intl.DateTimeFormat(locale, {
        weekday: 'short',
        timeZone: 'UTC',
    }).format(day);
}
