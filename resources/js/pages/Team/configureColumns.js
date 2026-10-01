export const configureColumns = (trans) => [
    {
        data: 'name',
        name: 'name',
        render: { display: '#nameCell' },
        title: trans('team.configure.groups.columns.name'),
    },
    {
        data: 'description',
        defaultContent: '',
        name: 'description',
        render: { display: '#descriptionCell' },
        title: trans('team.configure.groups.columns.description'),
    },
    {
        data: 'team_engagements_count',
        name: 'members',
        render: { display: '#membersCell' },
        title: trans('team.configure.groups.columns.members'),
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
