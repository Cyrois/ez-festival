export const entitlementColumns = (trans) => [
    {
        data: 'name',
        name: 'name',
        title: trans('credentials.entitlements.items.table.name'),
    },
    {
        data: 'labels',
        name: 'labels',
        orderable: false,
        render: { display: '#labelsCell' },
        searchable: false,
        title: trans('credentials.entitlements.items.table.labels'),
    },
    {
        data: 'balance',
        name: 'balance',
        title: trans('credentials.entitlements.items.table.balance'),
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
