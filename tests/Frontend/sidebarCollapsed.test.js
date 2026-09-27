import assert from 'node:assert/strict';
import test from 'node:test';
import {
    readSidebarCollapsed,
    sidebarStorageKey,
    shouldUseCompactSidebar,
    writeSidebarCollapsed,
} from '../../resources/js/composables/useSidebarCollapsed.js';

const storage = (initialValue = null) => ({
    value: initialValue,
    getItem(key) {
        assert.equal(key, sidebarStorageKey);

        return this.value;
    },
    setItem(key, value) {
        assert.equal(key, sidebarStorageKey);
        this.value = value;
    },
});

test('the collapsed sidebar preference is read before layout mount', () => {
    assert.equal(readSidebarCollapsed(storage('true')), true);
    assert.equal(readSidebarCollapsed(storage('false')), false);
    assert.equal(readSidebarCollapsed(storage()), false);
});

test('the collapsed sidebar preference persists boolean values', () => {
    const localStorage = storage();

    writeSidebarCollapsed(true, localStorage);
    assert.equal(localStorage.value, 'true');

    writeSidebarCollapsed(false, localStorage);
    assert.equal(localStorage.value, 'false');
});

test('unavailable browser storage falls back without throwing', () => {
    const unavailableStorage = {
        getItem() {
            throw new Error('Unavailable');
        },
        setItem() {
            throw new Error('Unavailable');
        },
    };

    assert.equal(readSidebarCollapsed(unavailableStorage), false);
    assert.doesNotThrow(() =>
        writeSidebarCollapsed(true, unavailableStorage),
    );
});

test('hover temporarily expands a collapsed sidebar without changing its preference', () => {
    assert.equal(shouldUseCompactSidebar(true, false), true);
    assert.equal(shouldUseCompactSidebar(true, true), false);
    assert.equal(shouldUseCompactSidebar(false, false), false);
    assert.equal(shouldUseCompactSidebar(false, true), false);
});
