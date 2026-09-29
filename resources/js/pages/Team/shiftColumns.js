// DataTables columns for Team → Scheduling → List.
//
// Display slots format the raw row data without changing the values DataTables
// sends to the server for ordering and searching.
export const shiftColumns = (trans) => [
    {
        data: 'name',
        defaultContent: '',
        name: 'name',
        render: { display: '#nameCell' },
        title: trans('team.scheduling.table.shift'),
    },
    {
        data: 'location',
        name: 'location',
        title: trans('team.scheduling.table.location'),
    },
    {
        data: 'starts_at',
        name: 'starts_at',
        render: { display: '#startCell' },
        title: trans('team.scheduling.table.start'),
    },
    {
        data: 'ends_at',
        name: 'ends_at',
        render: { display: '#endCell' },
        title: trans('team.scheduling.table.end'),
    },
];
