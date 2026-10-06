import assert from 'node:assert/strict';
import test from 'node:test';
import {
    massPersonalBreaks,
    translatedPersonalBreak,
    personalBreakPayload,
} from '../../resources/js/lib/personalBreaks.js';
import {
    translatedAssignment,
    resizedAssignment,
    draftRoster,
} from '../../resources/js/lib/shiftAssignments.js';
import {
    copiedShiftDraft,
    moveCopiedShift,
} from '../../resources/js/lib/shiftCopy.js';
import { timelineMinute } from '../../resources/js/lib/scheduleTimeline.js';
const shift = { starts_at: '2026-10-03T22:00', ends_at: '2026-10-04T04:00' };
const source = { id: 10, duration_minutes: 30, starts_at: '2026-10-04T00:30' };
const person = {
    id: 1,
    ...shift,
    breaks: [{ ...source, id: 11, shift_break_id: 10 }],
};

test('moving carries breaks across midnight with source intact; resizing removes breaks outside the new hours', () => {
    const custom = {
        ...person,
        starts_at: '2026-10-03T23:00',
        ends_at: '2026-10-04T02:00',
    };
    const moved = translatedAssignment(
        shift,
        custom,
        timelineMinute('2026-10-04T00:00'),
    );
    assert.equal(moved.breaks[0].starts_at, '2026-10-04T01:30');
    assert.equal(moved.breaks[0].shift_break_id, 10);
    assert.equal(moved.breaks[0].id, 11);
    const resized = resizedAssignment(
        shift,
        custom,
        'end',
        timelineMinute('2026-10-04T00:15'),
    );
    assert.deepEqual(resized.breaks, []);
    const draft = draftRoster({ ...shift, slots: [], assignments: [custom] }, [
        { id: 1, hours_mode: 'custom', ...resized },
    ]);
    assert.deepEqual(draft.assignments[0].breaks, []);
    assert.equal(custom.breaks[0].starts_at, source.starts_at);
});
test('copy remaps provenance and carries all personal breaks including full-shift rows and empty lists', () => {
    const draft = copiedShiftDraft({
        ...shift,
        breaks: [source],
        slots: [],
        assignments: [
            {
                team_engagement_id: 1,
                role_id: 2,
                hours_mode: 'full_shift',
                ...shift,
                breaks: [
                    {
                        duration_minutes: 15,
                        starts_at: '2026-10-04T00:45',
                        source_break_index: 0,
                    },
                ],
            },
            {
                team_engagement_id: 2,
                role_id: 2,
                hours_mode: 'custom',
                ...shift,
                breaks: [
                    {
                        duration_minutes: 30,
                        starts_at: '2026-10-04T01:00',
                        source_break_index: null,
                    },
                ],
            },
            {
                team_engagement_id: 3,
                role_id: 2,
                hours_mode: 'full_shift',
                ...shift,
                breaks: [],
            },
        ],
    });
    assert.equal(
        draft.assignment_additions[0].breaks[0].shift_break_key,
        draft.breaks[0]._key,
    );
    assert.equal(draft.assignment_additions[1].breaks[0].shift_break_id, null);
    const moved = moveCopiedShift(
        { ...draft, starts_at: '2026-10-04T22:00' },
        shift.starts_at,
    );
    assert.equal(
        moved.assignment_additions[0].breaks[0].starts_at,
        '2026-10-05T00:45',
    );
    assert.equal(
        moved.assignment_additions[1].breaks[0].starts_at,
        '2026-10-05T01:00',
    );
    assert.deepEqual(moved.assignment_additions[2].breaks, []);
});

test('grid resizing removes partial breaks at either edge and keeps fully fitting breaks unchanged', () => {
    const custom = {
        ...person,
        breaks: [
            {
                id: 11,
                shift_break_id: 10,
                duration_minutes: 30,
                starts_at: '2026-10-03T23:45',
            },
            {
                id: 12,
                shift_break_id: null,
                duration_minutes: 15,
                starts_at: '2026-10-04T00:15',
            },
            {
                id: 13,
                shift_break_id: null,
                duration_minutes: 30,
                starts_at: '2026-10-04T00:30',
            },
        ],
    };
    const end = resizedAssignment(
        shift,
        custom,
        'end',
        timelineMinute('2026-10-04T00:45'),
    );
    assert.deepEqual(
        end.breaks.map((row) => row.id),
        [11, 12],
    );
    const start = resizedAssignment(
        shift,
        custom,
        'start',
        timelineMinute('2026-10-04T00:00'),
    );
    assert.deepEqual(
        start.breaks.map((row) => row.id),
        [12, 13],
    );
    const boundary = resizedAssignment(
        shift,
        custom,
        'end',
        timelineMinute('2026-10-04T01:00'),
    );
    assert.deepEqual(boundary.breaks, custom.breaks);
    assert.notEqual(boundary.breaks[0], custom.breaks[0]);
    const extended = resizedAssignment(
        shift,
        { ...custom, ...end },
        'end',
        timelineMinute('2026-10-04T02:00'),
    );
    assert.deepEqual(
        extended.breaks.map((row) => row.id),
        [11, 12],
    );
    assert.equal(custom.breaks.length, 3);
});


test('mass add rejects all overlaps without mutation and only adds fitting independent breaks', () => {
    const people = [person, {...person, id: 2, breaks: []}, {...person, id: 3, ends_at: '2026-10-04T00:00', breaks: []}];
    const before = JSON.stringify(people);
    for (const starts_at of ['2026-10-04T00:30', '2026-10-04T00:45', '2026-10-04T00:15']) {
        const result = massPersonalBreaks(people, {...source, starts_at});
        assert.equal(result.conflict, true);
        assert.deepEqual(result.people, []);
        assert.equal(JSON.stringify(people), before);
    }
    const result = massPersonalBreaks(people, {...source, starts_at: '2026-10-04T01:00'});
    assert.equal(result.conflict, false);
    assert.equal(result.skipped, 1);
    assert.equal(result.people.length, 2);
    assert.equal(result.people[0].breaks.length, 2);
    assert.deepEqual(personalBreakPayload(result.people[1].breaks), [{duration_minutes: 30, starts_at: '2026-10-04T01:00', shift_break_id: null}]);
    assert.equal(JSON.stringify(people), before);
});


test('moving a personal break snaps to the nearest clock quarter, preserves its identity and length, and crosses midnight', () => {
    const original = {...person, breaks: [{...person.breaks[0], shift_break_key: 'draft-break-1'}]};
    const before = JSON.stringify(original);
    const move = (stamp) => translatedPersonalBreak(original, 0, timelineMinute(stamp));
    assert.equal(move('2026-10-03T23:53').starts_at, '2026-10-04T00:00');
    const moved = move('2026-10-04T00:08');
    assert.equal(moved.starts_at, '2026-10-04T00:15');
    assert.equal(moved.duration_minutes, 30);
    assert.equal(moved.id, 11);
    assert.equal(moved.shift_break_id, null);
    assert.equal(moved.shift_break_key, undefined);
    assert.equal(moved._day, '2026-10-04');
    assert.equal(moved._time, '00:15');
    assert.equal(move(source.starts_at).shift_break_id, 10);
    assert.equal(JSON.stringify(original), before);
});

test('personal break moves clamp to fitting quarter-hours and reject overlap without moving other breaks', () => {
    const bounded = {...person, starts_at: '2026-10-03T23:32', ends_at: '2026-10-04T02:09'};
    assert.equal(translatedPersonalBreak(bounded, 0, timelineMinute('2026-10-03T22:00')).starts_at, '2026-10-03T23:45');
    assert.equal(translatedPersonalBreak(bounded, 0, timelineMinute('2026-10-04T03:00')).starts_at, '2026-10-04T01:30');
    const busy = {...person, breaks: [...person.breaks, {duration_minutes: 15, starts_at: '2026-10-04T02:00'}]};
    assert.equal(translatedPersonalBreak(busy, 0, timelineMinute('2026-10-04T01:45')), null);
    assert.equal(translatedPersonalBreak(busy, 0, timelineMinute('2026-10-04T02:00')), null);
    assert.equal(translatedPersonalBreak(busy, 0, timelineMinute('2026-10-04T01:30')).starts_at, '2026-10-04T01:30');
    assert.equal(translatedPersonalBreak({...person, ends_at: '2026-10-03T22:15'}, 0, timelineMinute(source.starts_at)), null);
    assert.equal(translatedPersonalBreak(person, 5, timelineMinute(source.starts_at)), null);
});
