let nextDraftKey = 0;

export function newShiftSlot() {
    return {
        _key: `draft-${++nextDraftKey}`,
        id: null,
        role_id: '',
        role_name: '',
        needed: 1,
    };
}

export function draftShiftSlots(slots) {
    return [...slots]
        .sort((a, b) => a.sort_order - b.sort_order)
        .map((slot) => ({ ...slot, _key: `saved-${slot.id}` }));
}

export function totalShiftNeeds(slots) {
    return slots.reduce((total, slot) => {
        const needed = Number(slot.needed);
        return total + (Number.isInteger(needed) && needed > 0 ? needed : 0);
    }, 0);
}

export function shiftSlotPayload(slots, includeDraftKeys = false) {
    return slots.map(({ id, role_id, needed, _key }) => ({
        id,
        role_id,
        needed,
        ...(includeDraftKeys && id === null ? { client_key: _key } : {}),
    }));
}

// Bind errors to the submitted row identity, independent of display order or removals.
export function shiftSlotErrors(slots, errors) {
    const result = {};
    for (const [path, message] of Object.entries(errors)) {
        const match = /^slots\.(\d+)\.(\w+)$/.exec(path);
        const key = match && slots[Number(match[1])]?._key;
        if (key) {
            result[key] ??= {};
            result[key][match[2]] = message;
        }
    }
    return result;
}
