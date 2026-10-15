import assert from 'node:assert/strict';
import test from 'node:test';
import { checkInQuery } from '../../resources/js/pages/CheckIn/filters.js';

test('check-in filter query trims search and omits an empty pass', () => {
    assert.deepEqual(
        checkInQuery({
            type: 'artist',
            pass: '',
            status: 'incomplete',
            search: '  maya  ',
        }),
        {
            type: 'artist',
            pass: undefined,
            status: 'incomplete',
            search: 'maya',
        },
    );
});

test('check-in filters default to Incomplete and retain the Complete selection', () => {
    assert.equal(checkInQuery({}).status, 'incomplete');
    assert.deepEqual(checkInQuery({ type: 'team', pass: '12', status: 'complete', search: ' Maya ' }), {
        type: 'team', pass: '12', status: 'complete', search: 'Maya',
    });
});
