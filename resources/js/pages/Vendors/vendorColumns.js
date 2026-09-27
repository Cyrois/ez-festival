// DataTables columns for Vendors → Advancing.
//
// `render.display` names a Vue slot used for display only. Sorting, searching
// and type detection fall back to the raw cell value. Do not add `_:` keys:
// string values in a render object are read from the cell value, not the row,
// so `{ _: 'name' }` on `data: 'name'` reads `'Abe'.name` and breaks sorting,
// search and null cells.
export const vendorColumns = (trans) => [
    {
        data: 'name',
        name: 'vendor',
        render: { display: '#vendorCell' },
        title: trans('vendors.columns.vendor'),
    },
    {
        data: 'type',
        defaultContent: '',
        name: 'type',
        render: { display: '#typeCell' },
        title: trans('vendors.columns.type'),
    },
    {
        data: 'status',
        name: 'status',
        render: { display: '#statusCell' },
        title: trans('vendors.columns.status'),
    },
];
