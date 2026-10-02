import assert from 'node:assert/strict';
import test from 'node:test';
import {
    newShiftSlot,
    draftShiftSlots,
    totalShiftNeeds,
    shiftSlotPayload,
    shiftSlotErrors,
} from '../../resources/js/lib/shiftRoleSlots.js';
import { shiftColumns } from '../../resources/js/pages/Team/shiftColumns.js';

test('reload reconstructs insertion order and submission omits client metadata', () => {
    const rows = draftShiftSlots([
        {
            id: 7,
            role_id: 2,
            role_name: 'Lead',
            needed: 1,
            sort_order: 3,
        },
        {
            id: 6,
            role_id: 1,
            role_name: 'Crew',
            needed: 3,
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
    });
    assert.deepEqual(
        rows.map((r) => r.id),
        [6, 7],
    );
});

test('validation messages follow submitted row keys after row removal', () => {
    const rows = [newShiftSlot(), newShiftSlot()];
    const messages = shiftSlotErrors([...rows], {
        'slots.0.needed': 'Required',
        'slots.1.role_id': 'Unavailable',
    });
    const first = rows[1];
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
