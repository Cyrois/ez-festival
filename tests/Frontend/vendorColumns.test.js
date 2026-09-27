import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { vendorColumns } from '../../resources/js/pages/Vendors/vendorColumns.js';

const dom = new JSDOM('<!doctype html><html><body></body></html>', {
    pretendToBeVisual: true,
    url: 'http://localhost/',
});
const alerts = [];
dom.window.alert = (message) => alerts.push(message);
// Expose the jsdom browser globals DataTables expects.
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

const initials = (name) =>
    name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .toUpperCase();

// Mirror datatables.net-vue3: a '#slot' display renderer becomes a function
// that returns a rendered DOM node (here: avatar initials + name).
const withSlotRenderers = (columns) =>
    columns.map((column) => ({
        ...column,
        render: {
            ...column.render,
            display: (cellData) => {
                const node = document.createElement('div');
                node.textContent =
                    column.data === 'name'
                        ? `${initials(cellData)} ${cellData}`
                        : (cellData ?? '—');

                return node;
            },
        },
    }));

const vendors = [
    { id: 1, name: 'Bob', type: 'Food', status: 'idea' },
    { id: 2, name: 'Bo Diddley', type: null, status: 'contract_sent' },
    { id: 3, name: 'Abe', type: 'Food', status: 'confirmed' },
    { id: 4, name: 'Ab Zeta', type: 'Retail', status: 'declined' },
];

const mountTable = () => {
    const table = document.createElement('table');
    document.body.replaceChildren(table);

    return new DataTable(table, {
        columns: withSlotRenderers(vendorColumns((key) => key)),
        data: vendors.map((vendor) => ({ ...vendor })),
        order: [[0, 'asc']],
        paging: false,
    });
};

const visibleNames = (dt) =>
    dt
        .rows({ order: 'applied', search: 'applied' })
        .data()
        .toArray()
        .map((row) => row.name);

test('vendor columns never read sort/search values through render._', () => {
    for (const column of vendorColumns((key) => key)) {
        assert.deepEqual(Object.keys(column.render), ['display']);
    }
});

test('the vendor table sorts and searches raw values, with null types', () => {
    alerts.length = 0;
    const dt = mountTable();

    assert.deepEqual(visibleNames(dt), ['Ab Zeta', 'Abe', 'Bo Diddley', 'Bob']);

    dt.search('Abe').draw();
    assert.deepEqual(visibleNames(dt), ['Abe']);

    // Avatar initials in the rendered cell must not be searchable.
    dt.search('BD').draw();
    assert.deepEqual(visibleNames(dt), []);

    dt.search('Food').draw();
    assert.deepEqual(visibleNames(dt), ['Abe', 'Bob']);

    dt.search('contract_sent').draw();
    assert.deepEqual(visibleNames(dt), ['Bo Diddley']);

    dt.search('').draw();
    dt.search.fixed('vendorType', (_, row) => row.type === null).draw();
    assert.deepEqual(visibleNames(dt), ['Bo Diddley']);

    assert.deepEqual(alerts, []);
    dt.destroy();
});
