import { credentialsHome } from './credentialsNavigation.js';

export function getAppNavigation({
    features = {},
    permissions = {},
    isAdmin = false,
} = {}) {
    return [
        {
            key: 'home',
            href: '/dashboard',
            icon: ['fas', 'house'],
            enabled: true,
        },
        {
            key: 'check_in',
            href: '/check-in',
            icon: ['fas', 'clipboard-check'],
            enabled: true,
            tabs: [
                {
                    key: 'check_in.people',
                    href: '/check-in',
                    enabled: true,
                    exclude: ['/check-in/my-stock', '/check-in/inventory'],
                },
                {
                    key: 'check_in.my_stock',
                    href: '/check-in/my-stock',
                    enabled: true,
                },
                {
                    key: 'check_in.inventory',
                    href: '/check-in/inventory',
                    enabled: true,
                },
            ],
        },
        {
            key: 'artists',
            href: '/artists/advancing',
            icon: ['fas', 'music'],
            enabled: true,
        },
        {
            key: 'vendors',
            href: '/vendors/advancing',
            icon: ['fas', 'store'],
            enabled: true,
        },
        {
            key: 'patrons',
            href: '/patrons',
            icon: ['fas', 'address-book'],
            enabled: features.patrons ?? true,
        },
        {
            key: 'team',
            href: '/team/advancement',
            icon: ['fas', 'users'],
            enabled: features.team ?? true,
            tabs: [
                {
                    key: 'team.advancement',
                    href: '/team/advancement',
                    enabled: true,
                },
                {
                    key: 'team.scheduling',
                    href: '/team/scheduling',
                    enabled: true,
                },
                {
                    key: 'team.forms',
                    href: '/team/forms',
                    enabled: true,
                },
                {
                    key: 'team.reports',
                    href: '/team/reports',
                    enabled: true,
                },
                {
                    key: 'team.configure',
                    href: '/team/configure',
                    enabled: true,
                },
            ],
        },
        {
            key: 'credentials',
            href: '/credentials/passes',
            icon: ['fas', 'id-card'],
            enabled: true,
            tabs: [
                {
                    key: 'credentials.products',
                    href: null,
                    enabled: false,
                },
                {
                    key: 'credentials.passes',
                    href: '/credentials/passes',
                    enabled: true,
                },
                {
                    key: 'credentials.entitlements',
                    href: '/credentials/entitlements',
                    enabled: true,
                },
            ],
        },
        {
            key: 'meals',
            href: '/meals',
            icon: ['fas', 'utensils'],
            enabled: true,
            tabs: [
                {
                    key: 'meals.index',
                    href: '/meals',
                    enabled: true,
                    exclude: ['/meals/settings'],
                },
                {
                    key: 'meals.report',
                    href: '/reports',
                    enabled: true,
                },
                {
                    key: 'meals.settings',
                    href: '/meals/settings',
                    enabled: true,
                },
            ],
        },
    ]
        .map((item) => {
            if (item.key === 'check_in') {
                return {
                    ...item,
                    tabs: item.tabs.filter(
                        (tab) =>
                            tab.key === 'check_in.people' ||
                            permissions[
                                tab.key === 'check_in.inventory'
                                    ? 'inventory.manage'
                                    : 'checkin.edit'
                            ],
                    ),
                };
            }
            if (item.key === 'credentials') {
                const tabs = item.tabs.filter((tab) => {
                    if (tab.key === 'credentials.products') return isAdmin;
                    if (tab.key === 'credentials.passes')
                        return permissions['credentials.passes.edit'];
                    return (
                        permissions['credentials.entitlements.edit'] ||
                        permissions['credentials.stock.adjust'] ||
                        permissions['inventory.manage']
                    );
                });
                return { ...item, tabs, href: credentialsHome(permissions) };
            }
            if (!['team', 'meals'].includes(item.key)) return item;
            const tabs = item.tabs.filter(
                (child) =>
                    permissions?.[
                        {
                            'team.scheduling': 'scheduling.view',
                            'team.forms': 'forms.view',
                            'team.reports': 'team.hours_report.view',
                            'meals.report': 'meals.report.view',
                        }[child.key] ??
                            (item.key === 'meals' ? 'meals.view' : 'team.view')
                    ],
            );
            return { ...item, tabs, href: tabs[0]?.href };
        })
        .filter((item) => {
            if (['team', 'meals', 'credentials'].includes(item.key))
                return item.tabs.length > 0;
            const permission = {
                artists: 'artists.view',
                vendors: 'vendors.view',
                check_in: 'checkin.view',
                patrons: 'patrons.view',
            }[item.key];
            return !permission || permissions?.[permission];
        });
}

export function isNavigationItemActive(item, url) {
    const path = url.split(/[?#]/)[0];
    const matches = (href) =>
        href && (path === href || path.startsWith(`${href}/`));

    if (item.tabs) {
        return item.tabs.some((tab) => isNavigationItemActive(tab, path));
    }

    if (item.exclude?.some(matches)) return false;
    if (item.href === '/artists/advancing') {
        return (
            matches(item.href) ||
            matches('/artists/create') ||
            matches('/artists/engagements')
        );
    }
    if (item.href === '/vendors/advancing') {
        return (
            matches(item.href) ||
            matches('/vendors/create') ||
            matches('/vendors/engagements')
        );
    }
    if (item.key === 'team.advancement' && matches('/team/members'))
        return true;
    if (item.key === 'team.scheduling' && matches('/team/shifts')) return true;

    return Boolean(matches(item.href));
}
