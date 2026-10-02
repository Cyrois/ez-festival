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
        defaultContent: trans('data_table.empty_value'),
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
    {
        data: 'slots',
        name: 'roles',
        orderable: false,
        searchable: false,
        render: { display: '#rolesCell' },
        title: trans('team.scheduling.table.roles'),
    },
    {
        data: 'total_needs',
        name: 'total_needs',
        orderable: false,
        searchable: false,
        render: { display: '#needsCell' },
        title: trans('team.scheduling.table.needs'),
    },
    {
        data: null,
        defaultContent: '',
        name: 'open',
        orderable: false,
        render: { display: '#openCell' },
        searchable: false,
        title: '',
    },
];
