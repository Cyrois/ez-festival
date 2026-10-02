import assert from 'node:assert/strict';
import test from 'node:test';
import {
    newShiftSlot,
    draftShiftSlots,
    orderedShiftSlots,
    totalShiftNeeds,
    shiftSlotPayload,
    shiftSlotErrors,
} from '../../resources/js/lib/shiftRoleSlots.js';
import { shiftColumns } from '../../resources/js/pages/Team/shiftColumns.js';

test('supervisor toggles preserve insertion order, identities and the total', () => {
    const rows = [newShiftSlot(), newShiftSlot(), newShiftSlot()];
    rows[0].needed = 2;
    rows[1].needed = 1;
    rows[2].needed = 3;
    rows[1].is_supervisor = true;
    assert.deepEqual(
        orderedShiftSlots(rows).map((r) => r._key),
        [rows[1]._key, rows[0]._key, rows[2]._key],
    );
    rows[2].is_supervisor = true;
    assert.deepEqual(
        orderedShiftSlots(rows).map((r) => r._key),
        [rows[1]._key, rows[2]._key, rows[0]._key],
    );
    rows[1].is_supervisor = false;
    assert.deepEqual(
        orderedShiftSlots(rows).map((r) => r._key),
        [rows[2]._key, rows[0]._key, rows[1]._key],
    );
    assert.equal(totalShiftNeeds(rows), 6);
    assert.equal(rows[0].is_supervisor, false);
    assert.equal(rows[0].role_id, '');
});

test('reload reconstructs insertion order and submission omits client metadata', () => {
    const rows = draftShiftSlots([
        {
            id: 7,
            role_id: 2,
            role_name: 'Lead',
            needed: 1,
            is_supervisor: true,
            sort_order: 3,
        },
        {
            id: 6,
            role_id: 1,
            role_name: 'Crew',
            needed: 3,
            is_supervisor: false,
            sort_order: 0,
        },
    ]);
    assert.deepEqual(
        rows.map((r) => r.id),
        [6, 7],
    );
    assert.deepEqual(shiftSlotPayload(rows)[0], {
        id: 6,
        role_id: 1,
        needed: 3,
        is_supervisor: false,
    });
    assert.deepEqual(
        orderedShiftSlots(rows).map((r) => r.id),
        [7, 6],
    );
});

test('validation messages follow submitted row keys after supervisor sort and removal', () => {
    const rows = [newShiftSlot(), newShiftSlot()];
    const messages = shiftSlotErrors([...rows], {
        'slots.0.needed': 'Required',
        'slots.1.role_id': 'Unavailable',
    });
    rows[1].is_supervisor = true;
    const first = orderedShiftSlots(rows)[0];
    assert.equal(messages[first._key].role_id, 'Unavailable');
    rows.shift();
    assert.equal(messages[rows[0]._key].role_id, 'Unavailable');
    assert.equal(messages[rows[0]._key].needed, undefined);
});

test('incomplete counts keep the total finite without changing the submitted value', () => {
    const rows = ['', 'invalid', '-1', '1.5', '3'].map((needed) => ({
        ...newShiftSlot(),
        needed,
    }));
    assert.equal(totalShiftNeeds(rows), 3);
    assert.equal(shiftSlotPayload(rows)[0].needed, '');
});

test('the additional List columns do not shift existing server sort indexes', () => {
    const columns = shiftColumns((key) => key);
    assert.deepEqual(
        columns.slice(0, 4).map((column) => column.name),
        ['name', 'location', 'starts_at', 'ends_at'],
    );
    assert.equal(columns[4].orderable, false);
    assert.equal(columns[4].searchable, false);
    assert.equal(columns[5].orderable, false);
    assert.equal(columns[5].render.display, '#needsCell');
});
