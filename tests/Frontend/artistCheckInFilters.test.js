import assert from 'node:assert/strict';
import test from 'node:test';
import { checkInQuery } from '../../resources/js/pages/CheckIn/filters.js';

test('check-in filter query trims search and omits an empty pass', () => {
    assert.deepEqual(
        checkInQuery({
            type: 'artist',
            pass: '',
            status: 'partial',
            search: '  maya  ',
        }),
        {
            type: 'artist',
            pass: undefined,
            status: 'partial',
            search: 'maya',
        },
    );
});
