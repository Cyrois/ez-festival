import test from 'node:test';
import assert from 'node:assert/strict';
import { eventElapsedMinutes, eventLocalMinute } from '../../resources/js/lib/eventLocalTime.js';
import { validateShiftBreaks } from '../../resources/js/lib/shiftBreaks.js';
test('event elapsed time handles both DST transitions', () => {
    assert.equal(eventElapsedMinutes('2026-03-08T01:30', '2026-03-08T03:30', 'America/Vancouver'), 60);
    assert.equal(eventElapsedMinutes('2026-11-01T00:30', '2026-11-01T02:30', 'America/Vancouver'), 180);
    assert.equal(eventElapsedMinutes('2026-03-08T01:30', '2026-03-08T03:30', 'UTC'), 120);
});
test('missing and repeated local times are rejected in the event zone', () => {
    assert.ok(Number.isNaN(eventLocalMinute('2026-03-08T02:30', 'America/Vancouver')));
    assert.ok(Number.isNaN(eventLocalMinute('2026-11-01T01:30', 'America/Vancouver')));
    assert.ok(Number.isNaN(eventLocalMinute('2026-02-30T10:00', 'UTC')));
});
test('event calculations ignore the browser timezone', () => {
    const original = process.env.TZ;
    try {
        for (const timezone of ['UTC', 'Asia/Tokyo', 'America/New_York']) {
            process.env.TZ = timezone;
            assert.equal(eventElapsedMinutes('2026-03-08T01:30', '2026-03-08T03:30', 'America/Vancouver'), 60);
        }
    } finally { if (original === undefined) delete process.env.TZ; else process.env.TZ = original; }
});
test('break containment uses elapsed minutes across DST', () => {
    const breaks = [{ _key: 'break', starts_at: '2026-03-08T01:45', duration_minutes: 30 }];
    assert.deepEqual(validateShiftBreaks(breaks, '2026-03-08T01:30', '2026-03-08T03:30', [30], 'America/Vancouver'), {});
    assert.equal(validateShiftBreaks(breaks, '2026-03-08T01:30', '2026-03-08T03:00', [30], 'America/Vancouver').break.starts_at, 'team.scheduling.breaks.errors.containment');
});
