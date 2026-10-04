export const SCHEDULE_CELL_WIDTH = 56;
export const SCHEDULE_LABEL_WIDTH = 160;
export const SCHEDULE_SLOT_MINUTES = 30;
export const SCHEDULE_SLOT_COUNT = 48;

export function scheduleInitialScroll(firstShiftMinute) {
    if (firstShiftMinute === null || firstShiftMinute === undefined)
        return 12 * SCHEDULE_CELL_WIDTH;
    return Math.max(
        0,
        (firstShiftMinute / SCHEDULE_SLOT_MINUTES - 1) * SCHEDULE_CELL_WIDTH,
    );
}

export function scheduleShiftIsFilled(shift) {
    return shift.total_needs > 0 && shift.filled_count >= shift.total_needs;
}

// Date arithmetic is deliberately independent of the browser's timezone.
// Shift values are event-local wall-clock strings, not UTC instants.
export function scheduleDay(date, offset = 0) {
    const day = new Date(`${date}T00:00:00Z`);
    day.setUTCDate(day.getUTCDate() + offset);
    return day.toISOString().slice(0, 10);
}

export function scheduleDateLabel(date, locale, options = {}) {
    return new Intl.DateTimeFormat(locale, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        ...options,
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}

export function scheduleTimestamp(date, minutes) {
    if (minutes === 1440) return `${scheduleDay(date, 1)}T00:00`;
    return `${date}T${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

export function scheduleSelection(date, locationId, anchor, current) {
    const start = Math.min(anchor, current) * SCHEDULE_SLOT_MINUTES;
    const end = (Math.max(anchor, current) + 1) * SCHEDULE_SLOT_MINUTES;
    return {
        location_id: locationId,
        starts_at: scheduleTimestamp(date, start),
        ends_at: scheduleTimestamp(date, end),
        start,
        end,
    };
}

export function scheduleSlot(clientX, canvasLeft) {
    return Math.max(
        0,
        Math.min(
            SCHEDULE_SLOT_COUNT - 1,
            Math.floor((clientX - canvasLeft) / SCHEDULE_CELL_WIDTH),
        ),
    );
}

// UTC here is a coordinate system for event-local wall clocks, not an offset conversion.
export function timelineMinute(value) {
    return Date.parse(`${value}Z`) / 60000;
}

export function timelineIntersection(interval, bounds) {
    const origin = timelineMinute(bounds.starts_at);
    const start = Math.max(timelineMinute(interval.starts_at), origin);
    const end = Math.min(
        timelineMinute(interval.ends_at),
        timelineMinute(bounds.ends_at),
    );
    return end > start ? { start: start - origin, end: end - origin } : null;
}

export function scheduleInterval(shift, date) {
    return timelineIntersection(shift, {
        starts_at: `${date}T00:00`,
        ends_at: `${scheduleDay(date, 1)}T00:00`,
    });
}

export function shiftTimelineTicks(shift) {
    const start = timelineMinute(shift.starts_at);
    const end = timelineMinute(shift.ends_at);
    const values = [start];
    for (
        let minute = Math.floor(start / 60) * 60 + 60;
        minute < end;
        minute += 60
    )
        values.push(minute);
    values.push(end);
    return values.map((minute, index) => {
        const stamp = new Date(minute * 60000).toISOString();
        return {
            minute: minute - start,
            position: ((minute - start) / (end - start)) * 100,
            label: stamp.slice(11, 16),
            date: stamp.slice(11, 16) === '00:00' ? stamp.slice(0, 10) : '',
            showLabel:
                index === 0 ||
                index === values.length - 1 ||
                (minute - start >= 20 && end - minute >= 20),
        };
    });
}

export function shiftOverlapIntervals(assignment, shift) {
    const intervals = assignment.overlaps
        .map((overlap) =>
            timelineIntersection(
                {
                    starts_at:
                        assignment.starts_at > overlap.starts_at
                            ? assignment.starts_at
                            : overlap.starts_at,
                    ends_at:
                        assignment.ends_at < overlap.ends_at
                            ? assignment.ends_at
                            : overlap.ends_at,
                },
                shift,
            ),
        )
        .filter(Boolean)
        .sort((a, b) => a.start - b.start || a.end - b.end);
    const merged = [];
    for (const interval of intervals) {
        const previous = merged.at(-1);
        if (previous && interval.start <= previous.end)
            previous.end = Math.max(previous.end, interval.end);
        else merged.push({ ...interval });
    }
    return merged;
}

export function shiftHoursLabel(interval) {
    return interval.starts_at.slice(0, 10) === interval.ends_at.slice(0, 10)
        ? `${interval.starts_at.slice(11)}–${interval.ends_at.slice(11)}`
        : `${interval.starts_at.replace('T', ' ')}–${interval.ends_at.replace('T', ' ')}`;
}

export function scheduleLanes(shifts, date) {
    const intervals = shifts
        .map((shift) => ({ ...shift, interval: scheduleInterval(shift, date) }))
        .filter((shift) => shift.interval)
        .sort(
            (a, b) =>
                a.interval.start - b.interval.start ||
                a.interval.end - b.interval.end ||
                a.id - b.id,
        );
    const ends = [];
    return intervals.map((shift) => {
        let lane = ends.findIndex((end) => end <= shift.interval.start);
        if (lane === -1) lane = ends.length;
        ends[lane] = shift.interval.end;
        return { ...shift, lane };
    });
}

export function scheduleCreateHref(
    date,
    tab = 'schedule',
    selection = null,
    locationId = '',
    view = '',
) {
    const params = new URLSearchParams({
        return_tab: tab === 'list' ? 'list' : 'schedule',
        schedule_date: date,
    });
    if (selection) {
        params.set('location_id', selection.location_id);
        params.set('starts_at', selection.starts_at);
        params.set('ends_at', selection.ends_at);
    }
    if (locationId) params.set('schedule_location_id', locationId);
    if (view) params.set('schedule_view', view);
    return '/team/shifts/create?' + params;
}

export function scheduleShiftHref(
    id,
    date,
    tab = 'schedule',
    locationId = '',
    view = '',
) {
    const params = new URLSearchParams({
        return_tab: tab === 'list' ? 'list' : 'schedule',
        schedule_date: date,
    });
    if (locationId) params.set('schedule_location_id', locationId);
    if (view) params.set('schedule_view', view);
    return `/team/shifts/${id}?${params}`;
}

export function scheduleReturnHref(context = {}) {
    const params = new URLSearchParams({
        tab: context.return_tab === 'schedule' ? 'schedule' : 'list',
    });
    if (context.schedule_date) params.set('date', context.schedule_date);
    if (context.schedule_location_id)
        params.set('location_id', context.schedule_location_id);
    if (context.schedule_view) params.set('view', context.schedule_view);
    return '/team/scheduling?' + params;
}

// Older shared links without an explicit view retain their location roster.
export function scheduleLocationFromUrl(url, locations) {
    const value = new URLSearchParams(url.split('?')[1] ?? '').get(
        'location_id',
    );
    return (
        locations.find((location) => String(location.id) === value)?.id ?? ''
    );
}

export function schedulePageHref(date, tab, locationId = '', view = '') {
    const params = new URLSearchParams({ date, tab });
    if (locationId) params.set('location_id', locationId);
    if (view) params.set('view', view);
    return '/team/scheduling?' + params;
}

export function scheduleOverlapIntervals(assignment, date) {
    return assignment.overlaps
        .map((overlap) =>
            scheduleInterval(
                {
                    starts_at:
                        assignment.starts_at > overlap.starts_at
                            ? assignment.starts_at
                            : overlap.starts_at,
                    ends_at:
                        assignment.ends_at < overlap.ends_at
                            ? assignment.ends_at
                            : overlap.ends_at,
                },
                date,
            ),
        )
        .filter(Boolean);
}

export function scheduleViewFromUrl(url, locations) {
    const view = new URLSearchParams(url.split('?')[1] ?? '').get('view');
    if (['all_locations', 'location_shifts'].includes(view)) return view;
    return scheduleLocationFromUrl(url, locations)
        ? 'location_shifts'
        : 'all_locations';
}

export function shiftTimelineGrid(shift) {
    const start = timelineMinute(shift.starts_at);
    const end = timelineMinute(shift.ends_at);
    const minutes = [start];
    for (
        let minute = Math.floor(start / 30) * 30 + 30;
        minute < end;
        minute += 30
    )
        minutes.push(minute);
    minutes.push(end);
    return minutes.map((minute) => ({
        minute: minute - start,
        position: ((minute - start) / (end - start)) * 100,
    }));
}

export const OPEN_ROLE_PATTERN =
    'border-muted/40 bg-page bg-[repeating-linear-gradient(135deg,transparent,transparent_4px,color-mix(in_srgb,var(--color-muted)_20%,transparent)_4px,color-mix(in_srgb,var(--color-muted)_20%,transparent)_6px)]';
