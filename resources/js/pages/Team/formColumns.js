export const formColumns = (trans) => [
    {
        data: 'name',
        name: 'name',
        title: trans('team.forms.table.name'),
    },
    {
        data: 'status',
        name: 'status',
        render: { display: '#statusCell' },
        title: trans('team.forms.table.status'),
    },
    {
        data: 'public_url',
        name: 'public_link',
        orderable: false,
        render: { display: '#publicLinkCell' },
        searchable: false,
        title: trans('team.forms.table.public_link'),
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
