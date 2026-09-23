import assert from 'node:assert/strict';
import test from 'node:test';
import { filterArtistCheckIns } from '../../resources/js/pages/Artists/checkInFilters.js';

const engagements = [
    {
        id: 1,
        name: 'River Hollow',
        contact: { name: 'Maya Chen', email: 'maya@example.com' },
        check_in_status: 'partial',
    },
    {
        id: 2,
        name: 'Amber Field',
        contact: { name: 'Jordan Blake', email: 'jordan@example.com' },
        check_in_status: 'not_started',
    },
    {
        id: 3,
        name: 'Zero Expected',
        contact: null,
        check_in_status: 'complete',
    },
];

test('search matches artist, contact name, and contact email case-insensitively', () => {
    assert.deepEqual(
        filterArtistCheckIns(engagements, ' river ').map(({ id }) => id),
        [1],
    );
    assert.deepEqual(
        filterArtistCheckIns(engagements, 'JORDAN').map(({ id }) => id),
        [2],
    );
    assert.deepEqual(
        filterArtistCheckIns(engagements, 'maya@example').map(({ id }) => id),
        [1],
    );
});

test('status chips return only the selected state', () => {
    assert.deepEqual(
        filterArtistCheckIns(engagements, '', 'not_started').map(
            ({ id }) => id,
        ),
        [2],
    );
    assert.deepEqual(
        filterArtistCheckIns(engagements, '', 'complete').map(({ id }) => id),
        [3],
    );
});

test('search and status filters combine and All returns every engagement', () => {
    assert.deepEqual(
        filterArtistCheckIns(engagements, 'maya', 'partial').map(
            ({ id }) => id,
        ),
        [1],
    );
    assert.deepEqual(filterArtistCheckIns(engagements), engagements);
});
