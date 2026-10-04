const formatters = new Map();
function formatter(timeZone) {
    if (!formatters.has(timeZone))
        formatters.set(
            timeZone,
            new Intl.DateTimeFormat('en-GB', {
                timeZone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hourCycle: 'h23',
            }),
        );
    return formatters.get(timeZone);
}
function parts(stamp, timeZone) {
    return Object.fromEntries(
        formatter(timeZone)
            .formatToParts(new Date(stamp))
            .map(({ type, value }) => [type, value]),
    );
}
function wallStamp(values) {
    return Date.UTC(
        Number(values.year),
        Number(values.month) - 1,
        Number(values.day),
        Number(values.hour),
        Number(values.minute),
    );
}
// A local minute must have exactly one offset interpretation in the event zone.
export function eventLocalMinute(value, timeZone = 'UTC') {
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value ?? '')) return NaN;
    const wall = Date.parse(`${value}Z`);
    if (
        !Number.isFinite(wall) ||
        new Date(wall).toISOString().slice(0, 16) !== value
    )
        return NaN;
    const offsets = new Set();
    for (let hours = -48; hours <= 48; hours += 6) {
        const sample = wall + hours * 3600000;
        offsets.add(wallStamp(parts(sample, timeZone)) - sample);
    }
    const candidates = [...offsets]
        .map((offset) => wall - offset)
        .filter((stamp) => wallStamp(parts(stamp, timeZone)) === wall);
    return candidates.length === 1 ? candidates[0] / 60000 : NaN;
}
export function eventElapsedMinutes(start, end, timeZone = 'UTC') {
    return eventLocalMinute(end, timeZone) - eventLocalMinute(start, timeZone);
}
