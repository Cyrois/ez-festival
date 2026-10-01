import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { entitlementColumns } from '../../resources/js/pages/Credentials/entitlementColumns.js';
import { passColumns } from '../../resources/js/pages/Credentials/passColumns.js';
import { eventColumns } from '../../resources/js/pages/Settings/Events/eventColumns.js';

const dom = new JSDOM('<!doctype html><html><body></body></html>', {
    pretendToBeVisual: true,
    url: 'http://localhost/',
});
dom.window.alert = () => {};
Object.defineProperty(globalThis, 'window', {
    configurable: true,
    value: dom.window,
});
for (const key of Object.getOwnPropertyNames(dom.window)) {
    if (!(key in globalThis)) {
        Object.defineProperty(globalThis, key, {
            configurable: true,
            value: dom.window[key],
        });
    }
}
const { default: DataTable } = await import('datatables.net');

const withSlotRenderers = (columns) =>
    columns.map((column) => ({
        ...column,
        render:
            column.render?.display?.startsWith?.('#')
                ? {
                      ...column.render,
                      display: (value) => {
                          const node = document.createElement('span');
                          node.textContent = Array.isArray(value)
                              ? value.map((item) => item.name).join(', ')
                              : (value ?? '');

                          return node;
                      },
                  }
                : column.render,
    }));

const exerciseBrowserList = (columns, rows, search) => {
    const table = document.createElement('table');
    document.body.replaceChildren(table);
    const dataTable = new DataTable(table, {
        columns: withSlotRenderers(columns((key) => key)),
        data: rows,
        pageLength: 25,
    });

    assert.equal(dataTable.page.info().pages, 2);
    assert.equal(dataTable.rows({ page: 'current' }).count(), 25);

    dataTable.search(search).draw();
    assert.equal(dataTable.rows({ search: 'applied' }).count(), 1);

    dataTable.destroy();
};

test('Entitlements browser-pages and searches its raw item names', () => {
    exerciseBrowserList(
        entitlementColumns,
        Array.from({ length: 26 }, (_, index) => ({
            id: index + 1,
            name: index === 25 ? 'Needle entitlement' : `Item ${index + 1}`,
            labels: [],
            balance: index,
        })),
        'Needle entitlement',
    );
});

test('Passes browser-pages and searches its raw pass names', () => {
    exerciseBrowserList(
        passColumns,
        Array.from({ length: 26 }, (_, index) => ({
            id: index + 1,
            name: index === 25 ? 'Needle pass' : `Pass ${index + 1}`,
            assigned_count: index,
            max_assignments: null,
            labels: [],
        })),
        'Needle pass',
    );
});

test('Settings Events browser-pages and searches raw event values', () => {
    exerciseBrowserList(
        eventColumns,
        Array.from({ length: 26 }, (_, index) => ({
            id: index + 1,
            name: index === 25 ? 'Needle festival' : `Festival ${index + 1}`,
            starts_on: '2027-07-10',
            ends_on: '2027-07-12',
            is_locked: false,
            is_active: false,
            is_past: false,
        })),
        'Needle festival',
    );
});

test('the three pages enable browser paging and wire their search controls', () => {
    const read = (path) =>
        readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
    const pages = [
        read('resources/js/pages/Credentials/Entitlements.vue'),
        read('resources/js/pages/Credentials/Passes.vue'),
        read('resources/js/pages/Settings/Events/Index.vue'),
    ];

    for (const page of pages) {
        assert.match(page, /pageLength: 25/);
        assert.match(page, /table\.value\?\.(search|filterRows)/);
        assert.doesNotMatch(page, /serverSide: true/);
    }
});
