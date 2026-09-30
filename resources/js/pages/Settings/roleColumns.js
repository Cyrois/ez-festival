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
        data: 'can_read_team_notes',
        name: 'team_notes',
        orderable: false,
        render: { display: '#teamNotesCell' },
        searchable: false,
        title: trans('settings.roles.columns.team_notes'),
    },
    {
        data: null,
        defaultContent: '',
        name: 'actions',
        orderable: false,
        render: { display: '#actionsCell' },
        searchable: false,
        title: trans('settings.roles.columns.actions'),
    },
];
