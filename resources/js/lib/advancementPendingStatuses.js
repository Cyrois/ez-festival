// Optimistic Columns moves that stay correct while status PATCHes run in parallel.
// Every visit gets an increasing request id so stale reloads cannot undo a later move.

export const createPendingState = () => ({
    entries: {},
    active: [],
    snapshot: null,
    snapshotFrom: null,
});

const statusesById = (items) =>
    Object.fromEntries(items.map((item) => [item.id, item.status]));

const keepEntries = (entries, keep) =>
    Object.fromEntries(
        Object.entries(entries).filter(([id, entry]) => keep(entry, id)),
    );

const settle = (state, entry, id) => {
    if (!entry.settled || entry.waitFor.length) return 'keep';
    if (!state.snapshot) return 'refresh';
    if (
        state.snapshotFrom > entry.watermark ||
        state.snapshot[id] === undefined ||
        state.snapshot[id] === entry.status
    ) {
        return 'drop';
    }
    return 'refresh';
};

export const startVisit = (state, requestId) => ({
    ...state,
    active: [...state.active, requestId],
});

export const startMove = (state, id, status, requestId) => {
    const next = startVisit(state, requestId);
    return {
        ...next,
        entries: {
            ...next.entries,
            [id]: {
                status,
                requestId,
                settled: false,
                waitFor: [],
                watermark: requestId,
            },
        },
    };
};

export const failMove = (state, id, requestId) =>
    state.entries[id]?.requestId === requestId
        ? {
              ...state,
              entries: keepEntries(
                  state.entries,
                  (entry, key) => key !== `${id}`,
              ),
          }
        : state;

export const succeedMove = (state, id, requestId) => {
    const entry = state.entries[id];
    if (entry?.requestId !== requestId) return state;
    return {
        ...state,
        entries: {
            ...state.entries,
            [id]: {
                ...entry,
                settled: true,
                waitFor: state.active.filter((active) => active !== requestId),
                watermark: Math.max(requestId, ...state.active),
            },
        },
    };
};

export const applySnapshot = (state, items, requestId) => {
    const next = {
        ...state,
        snapshot: statusesById(items),
        snapshotFrom: requestId,
    };
    return {
        ...next,
        entries: keepEntries(
            next.entries,
            (entry, id) => settle(next, entry, id) !== 'drop',
        ),
    };
};

export const forgetSnapshot = (state) => ({
    ...state,
    snapshot: null,
    snapshotFrom: null,
});

export const finishVisit = (state, requestId) => {
    const next = {
        ...state,
        active: state.active.filter((active) => active !== requestId),
        entries: {},
    };
    let needsRefresh = false;
    Object.entries(state.entries).forEach(([id, entry]) => {
        if (!entry.settled && entry.requestId === requestId) return;
        const current = {
            ...entry,
            waitFor: entry.waitFor.filter((active) => active !== requestId),
        };
        const outcome = settle(state, current, id);
        if (outcome !== 'drop') {
            next.entries[id] = current;
            needsRefresh ||= outcome === 'refresh';
        }
    });
    return { state: next, needsRefresh };
};

export const dropSettled = (state) => ({
    ...state,
    entries: keepEntries(
        state.entries,
        (entry) => !entry.settled || entry.waitFor.length > 0,
    ),
});

export const reconcileWithProps = (state, items) => {
    const statuses = statusesById(items);
    return {
        ...state,
        entries: keepEntries(
            state.entries,
            (entry, id) =>
                !entry.settled ||
                entry.waitFor.length > 0 ||
                (statuses[id] !== undefined && statuses[id] !== entry.status),
        ),
    };
};

export const movingIds = (state) =>
    Object.entries(state.entries)
        .filter(([, entry]) => !entry.settled)
        .map(([id]) => Number(id));

export const overlayItems = (state, items) =>
    items.map((item) =>
        state.entries[item.id]
            ? { ...item, status: state.entries[item.id].status }
            : item,
    );

export const overlayCounts = (state, items, serverCounts) => {
    const counts = { ...serverCounts };
    items.forEach((item) => {
        const pending = state.entries[item.id]?.status;
        if (pending && pending !== item.status) {
            counts[item.status] -= 1;
            counts[pending] += 1;
        }
    });
    return counts;
};
