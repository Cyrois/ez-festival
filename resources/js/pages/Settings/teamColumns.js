// DataTables columns for Global Settings → Team.
// Display slots preserve raw values for sorting and rendering.
export const teamColumns = (trans) => [
    {
        data: 'name',
        name: 'name',
        render: { display: '#personCell' },
        title: trans('settings.team.columns.name'),
    },
    {
        data: 'email',
        name: 'email',
        title: trans('settings.team.columns.email'),
    },
    {
        data: 'events',
        name: 'events',
        orderable: false,
        render: { display: '#eventsCell' },
        searchable: false,
        title: trans('settings.team.columns.events'),
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
