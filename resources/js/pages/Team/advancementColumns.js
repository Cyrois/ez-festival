export const advancementColumns = (trans) => [
    {
        data: 'name',
        name: 'member',
        render: { display: '#memberCell' },
        title: trans('team.advancement.columns.member'),
    },
    {
        data: 'employment_type',
        name: 'type',
        render: { display: '#typeCell' },
        title: trans('team.advancement.columns.type'),
    },
    {
        data: 'status',
        name: 'status',
        render: { display: '#statusCell' },
        title: trans('team.advancement.columns.status'),
    },
    {
        data: null,
        defaultContent: '',
        name: 'group',
        orderable: false,
        render: { display: '#groupCell' },
        searchable: false,
        title: trans('team.advancement.columns.group'),
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
