// DataTables columns for Global Settings → Roles.
// Display slots preserve raw values for server-side ordering.
export const roleColumns = (trans) => [
    {
        data: 'name',
        name: 'role',
        render: { display: '#roleCell' },
        title: trans('settings.roles.columns.role'),
    },
    {
        data: 'people_count',
        name: 'people',
        orderable: false,
        render: { display: '#peopleCell' },
        searchable: false,
        title: trans('settings.roles.columns.people'),
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
