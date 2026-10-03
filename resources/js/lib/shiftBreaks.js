let nextDraftKey = 0;

// Floating event-local timestamps: use UTC only as a calendar arithmetic coordinate.
export function wallMinutes(value) {
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value ?? '')) return NaN;
    const date = new Date(`${value}Z`);
    return Number.isFinite(date.getTime()) &&
        date.toISOString().slice(0, 16) === value
        ? date.getTime() / 60000
        : NaN;
}

export function shiftBreakDays(start, end) {
    const first = wallMinutes(start);
    const last = wallMinutes(end);
    if (!Number.isFinite(first) || !Number.isFinite(last) || first >= last)
        return [];
    const days = [];
    const cursor = new Date(`${start.slice(0, 10)}T00:00:00Z`);
    while (cursor.getTime() < last * 60000) {
        days.push(cursor.toISOString().slice(0, 10));
        cursor.setUTCDate(cursor.getUTCDate() + 1);
    }
    return days;
}

export function newShiftBreak(defaultDuration, day = '') {
    return {
        _key: `draft-break-${++nextDraftKey}`,
        _day: day,
        _time: '',
        id: null,
        duration_minutes: defaultDuration,
        starts_at: '',
    };
}

export function draftShiftBreaks(breaks) {
    return [...breaks]
        .sort((a, b) => a.sort_order - b.sort_order)
        .map((row) => ({
            ...row,
            _key: `saved-break-${row.id}`,
            _day: row.starts_at.slice(0, 10),
            _time: row.starts_at.slice(11, 16),
        }));
}

export function updateShiftBreak(row, field, value) {
    const updated = { ...row, [field]: value };
    if (field === '_day' || field === '_time') {
        updated.starts_at =
            updated._day && updated._time
                ? `${updated._day}T${updated._time}`
                : '';
    }
    return updated;
}

export function shiftBreakPayload(breaks) {
    return breaks.map(({ id, duration_minutes, starts_at }) => ({
        ...(id != null ? { id } : {}),
        duration_minutes: Number(duration_minutes),
        starts_at,
    }));
}

export function shiftBreakErrors(breaks, errors) {
    const result = {};
    for (const [path, message] of Object.entries(errors)) {
        const match = /^breaks\.(\d+)\.(\w+)$/.exec(path);
        const key = match && breaks[Number(match[1])]?._key;
        if (key) {
            result[key] ??= {};
            result[key][match[2]] = message;
        }
    }
    return result;
}

export function validateShiftBreaks(breaks, start, end, durations) {
    const result = {};
    const intervals = [];
    const first = wallMinutes(start);
    const last = wallMinutes(end);
    const validBounds =
        Number.isFinite(first) && Number.isFinite(last) && first < last;
    for (const row of breaks) {
        const errors = {};
        const duration = Number(row.duration_minutes);
        const validDuration =
            Number.isInteger(duration) && durations.includes(duration);
        if (!validDuration)
            errors.duration_minutes = 'team.scheduling.breaks.errors.duration';
        const time = wallMinutes(row.starts_at);
        if (!Number.isFinite(time))
            errors.starts_at = 'team.scheduling.breaks.errors.start';
        else if (!validBounds)
            errors.starts_at = 'team.scheduling.breaks.errors.shift_times';
        else if (validDuration && (time < first || time + duration > last))
            errors.starts_at = 'team.scheduling.breaks.errors.containment';
        if (Number.isFinite(time) && validDuration)
            intervals.push({
                key: row._key,
                start: time,
                end: time + duration,
            });
        if (Object.keys(errors).length) result[row._key] = errors;
    }
    intervals.sort((a, b) => a.start - b.start);
    let latestEnd = -Infinity;
    let latestKey;
    for (const interval of intervals) {
        if (interval.start < latestEnd) {
            result[interval.key] ??= {};
            result[interval.key].starts_at =
                'team.scheduling.breaks.errors.overlap';
            result[latestKey] ??= {};
            result[latestKey].starts_at =
                'team.scheduling.breaks.errors.overlap';
        }
        if (interval.end > latestEnd) {
            latestEnd = interval.end;
            latestKey = interval.key;
        }
    }
    return result;
}
