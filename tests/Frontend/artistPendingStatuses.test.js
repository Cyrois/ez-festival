import assert from 'node:assert/strict';
import test from 'node:test';
import {
    applySnapshot,
    createPendingState,
    dropSettled,
    failMove,
    finishVisit,
    forgetSnapshot,
    movingIds,
    overlayCounts,
    overlayItems,
    reconcileWithProps,
    startMove,
    startVisit,
    succeedMove,
} from '../../resources/js/lib/advancementPendingStatuses.js';

const cards = (a, b) => [
    { id: 1, status: a },
    { id: 2, status: b },
];
const counts = (items) =>
    items.reduce(
        (total, item) => ({
            ...total,
            [item.status]: (total[item.status] ?? 0) + 1,
        }),
        { idea: 0, outreach: 0, confirmed: 0 },
    );
const statusOf = (state, items, id) =>
    overlayItems(state, items).find((item) => item.id === id).status;
const finish = (state, requestId) => finishVisit(state, requestId).state;

test('a stale reload from an earlier move cannot snap a later move back', () => {
    let state = createPendingState();
    state = startMove(state, 1, 'confirmed', 1); // move A
    state = startMove(state, 2, 'outreach', 2); // move B

    // B commits and its own reload (fresh) lands first.
    const fresh = cards('idea', 'outreach');
    state = succeedMove(state, 2, 2);
    state = applySnapshot(state, fresh, 2);
    state = finish(state, 2);
    assert.equal(statusOf(state, fresh, 2), 'outreach');

    // A's reload read the DB before B committed and lands last.
    const stale = cards('confirmed', 'idea');
    state = succeedMove(state, 1, 1);
    state = applySnapshot(state, stale, 1);
    state = reconcileWithProps(state, stale);
    assert.equal(statusOf(state, stale, 2), 'outreach');
    assert.deepEqual(overlayCounts(state, stale, counts(stale)), {
        idea: 0,
        outreach: 1,
        confirmed: 1,
    });

    // Once A finishes, B still disagrees with the last snapshot: refresh.
    const result = finishVisit(state, 1);
    assert.equal(result.needsRefresh, true);
    assert.deepEqual(movingIds(result.state), []);

    // A refresh started afterwards is authoritative and clears the overlay.
    state = startVisit(result.state, 3);
    state = applySnapshot(state, cards('confirmed', 'outreach'), 3);
    state = finish(state, 3);
    assert.deepEqual(state.entries, {});
});

test('a move stays pending until a reload confirms it, not on finish', () => {
    let state = startMove(createPendingState(), 1, 'confirmed', 1);
    state = startMove(state, 2, 'outreach', 2);
    assert.deepEqual(movingIds(state), [1, 2]);

    // B's own reload arrives while A is still in flight: keep B's overlay.
    state = succeedMove(state, 2, 2);
    state = applySnapshot(state, cards('idea', 'outreach'), 2);
    const result = finishVisit(state, 2);
    assert.equal(result.needsRefresh, false);
    assert.equal(result.state.entries[2].status, 'outreach');
    assert.deepEqual(movingIds(result.state), [1]);

    // A's reload (not stale here) confirms both.
    state = succeedMove(result.state, 1, 1);
    state = applySnapshot(state, cards('confirmed', 'outreach'), 1);
    state = finish(state, 1);
    assert.deepEqual(state.entries, {});
});

test('a failed move reverts immediately', () => {
    let state = startMove(createPendingState(), 1, 'confirmed', 1);
    state = failMove(state, 1, 1);
    state = forgetSnapshot(state);
    assert.equal(statusOf(state, cards('idea', 'idea'), 1), 'idea');
    assert.equal(finishVisit(state, 1).needsRefresh, false);
});

test('a move that finishes without success or validation error reverts', () => {
    let state = startMove(createPendingState(), 1, 'confirmed', 1);
    state = finish(state, 1);
    assert.deepEqual(state.entries, {});
});

test('a card filtered out of props does not leak a pending entry', () => {
    let state = startMove(createPendingState(), 1, 'confirmed', 1);
    state = startVisit(state, 2); // filter reload in flight
    state = succeedMove(state, 1, 1);
    const withoutCard = [{ id: 2, status: 'idea' }];
    state = applySnapshot(state, withoutCard, 1);
    state = finish(state, 1);
    assert.equal(state.entries[1].status, 'confirmed');

    state = applySnapshot(state, withoutCard, 2);
    state = finish(state, 2);
    assert.deepEqual(state.entries, {});

    let settled = startMove(createPendingState(), 1, 'confirmed', 3);
    settled = succeedMove(settled, 1, 3);
    settled = { ...settled, entries: { 1: settled.entries[1] } };
    assert.deepEqual(reconcileWithProps(settled, withoutCard).entries, {});
});

test('a stale failed-page response triggers a refresh instead of trusting old data', () => {
    let state = startMove(createPendingState(), 1, 'confirmed', 1);
    state = startMove(state, 2, 'outreach', 2);
    state = succeedMove(state, 1, 1);
    state = applySnapshot(state, cards('confirmed', 'idea'), 1);
    state = finish(state, 1);
    state = failMove(state, 2, 2);
    state = forgetSnapshot(state);
    const result = finishVisit(state, 2);
    assert.equal(result.needsRefresh, true);
    assert.equal(result.state.entries[1].status, 'confirmed');
});

test('a failed refresh hands the card back to server props', () => {
    let state = startMove(createPendingState(), 1, 'confirmed', 1);
    state = succeedMove(state, 1, 1);
    state = forgetSnapshot(state);
    const result = finishVisit(state, 1);
    assert.equal(result.needsRefresh, true);

    state = startVisit(result.state, 2);
    state = forgetSnapshot(state);
    state = dropSettled(finishVisit(state, 2).state);
    assert.deepEqual(state.entries, {});
});
