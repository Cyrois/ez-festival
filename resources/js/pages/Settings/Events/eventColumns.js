export const eventColumns = (trans) => [
    {
        data: 'name',
        name: 'event',
        render: { display: '#eventCell' },
        title: trans('settings.events.columns.event'),
    },
    {
        data: 'starts_on',
        defaultContent: trans('data_table.empty_value'),
        name: 'starts_on',
        title: trans('settings.events.columns.start_date'),
    },
    {
        data: 'ends_on',
        defaultContent: trans('data_table.empty_value'),
        name: 'ends_on',
        title: trans('settings.events.columns.end_date'),
    },
    {
        data: null,
        defaultContent: '',
        name: 'status',
        orderable: false,
        render: { display: '#statusCell' },
        searchable: false,
        title: trans('settings.events.columns.status'),
    },
    {
        data: null,
        defaultContent: '',
        name: 'actions',
        orderable: false,
        render: { display: '#actionsCell' },
        searchable: false,
        title: '',
    },
];
