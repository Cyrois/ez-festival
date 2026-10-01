export const passColumns = (trans) => [
    {
        data: 'name',
        name: 'name',
        title: trans('credentials.passes.table.name'),
    },
    {
        data: null,
        name: 'usage',
        orderable: false,
        render: { display: '#usageCell' },
        searchable: false,
        title: trans('credentials.passes.table.usage'),
    },
    {
        data: 'labels',
        name: 'labels',
        orderable: false,
        render: { display: '#labelsCell' },
        searchable: false,
        title: trans('credentials.passes.table.labels'),
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
