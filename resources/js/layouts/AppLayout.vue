<script setup>
import { Toast } from '../components/ui/toast';
import { Icon } from '../components/ui/icon';
import SidebarCollapseButton from '../components/navigation/SidebarCollapseButton.vue';
import SidebarNavItem from '../components/navigation/SidebarNavItem.vue';
import UserAccountControls from '../components/navigation/UserAccountControls.vue';
import { useInertiaErrorToast } from '../composables/useInertiaErrorToast';
import {
    shouldUseCompactSidebar,
    useSidebarCollapsed,
} from '../composables/useSidebarCollapsed';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    backHref: {
        type: String,
        default: '',
    },
    backLabel: {
        type: String,
        default: '',
    },
    settingsNav: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
useInertiaErrorToast();

const navOpen = ref(false);
const sidebarHovered = ref(false);
const sidebarCollapsed = useSidebarCollapsed({ enabled: !props.settingsNav });
const sidebarCompact = computed(() =>
    shouldUseCompactSidebar(sidebarCollapsed.value, sidebarHovered.value),
);

watch(
    () => page.url,
    () => {
        navOpen.value = false;
    },
);

const user = computed(() => page.props.auth?.user);
const eventName = computed(() => page.props.activeEvent?.name ?? null);
const organizationName = computed(() => page.props.organization?.name ?? null);
const currentPath = computed(() => page.url.split('?')[0]);
const features = computed(() => page.props.features ?? {});

const navItems = computed(() =>
    [
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
            enabled: features.value.patrons ?? true,
        },
        {
            key: 'team',
            href: '/team/advancement',
            icon: ['fas', 'users'],
            enabled: features.value.team ?? true,
            children: [
                {
                    key: 'team.advancement',
                    href: '/team/advancement',
                    icon: ['fas', 'list'],
                    enabled: true,
                },
                {
                    key: 'team.scheduling',
                    href: '/team/scheduling',
                    icon: ['fas', 'calendar-days'],
                    enabled: true,
                },
                {
                    key: 'team.forms',
                    href: '/team/forms',
                    icon: ['fas', 'clipboard-check'],
                    enabled: true,
                },
                {
                    key: 'team.configure',
                    href: '/team/configure',
                    icon: ['fas', 'gear'],
                    enabled: true,
                },
            ],
        },
        {
            key: 'credentials',
            href: '/credentials/passes',
            icon: ['fas', 'id-card'],
            enabled: true,
            children: [
                {
                    key: 'credentials.products',
                    href: null,
                    icon: ['fas', 'store'],
                    enabled: false,
                },
                {
                    key: 'credentials.passes',
                    href: '/credentials/passes',
                    icon: ['fas', 'id-card'],
                    enabled: true,
                },
                {
                    key: 'credentials.entitlements',
                    href: '/credentials/entitlements',
                    icon: ['fas', 'lock-open'],
                    enabled: true,
                },
            ],
        },
    ]
        .map((item) => {
            if (item.key !== 'team') return item;
            const children = item.children.filter(
                (child) =>
                    page.props.permissions?.[
                        {
                            'team.scheduling': 'scheduling.view',
                            'team.forms': 'forms.view',
                        }[child.key] ?? 'team.view'
                    ],
            );
            return { ...item, children, href: children[0]?.href };
        })
        .filter((item) => {
            if (item.key === 'team') return item.children.length > 0;
            const permission = {
                artists: 'artists.view',
                vendors: 'vendors.view',
                check_in: 'checkin.view',
                patrons: 'patrons.view',
            }[item.key];
            return item.key === 'credentials'
                ? user.value?.is_admin
                : !permission || page.props.permissions?.[permission];
        }),
);

const settingsActive = computed(
    () =>
        currentPath.value === '/settings' ||
        currentPath.value.startsWith('/settings/'),
);

const crumbItems = computed(() => {
    if (props.breadcrumbs.length > 0) {
        return props.breadcrumbs;
    }

    return [{ label: props.title }];
});

const isActive = (href) => {
    if (!href) {
        return false;
    }

    if (href === '/artists/advancing') {
        return (
            currentPath.value === href ||
            currentPath.value === '/artists/create' ||
            currentPath.value.startsWith('/artists/engagements/')
        );
    }

    if (href === '/vendors/advancing') {
        return (
            currentPath.value === href ||
            currentPath.value === '/vendors/create' ||
            currentPath.value.startsWith('/vendors/engagements/')
        );
    }

    return (
        currentPath.value === href || currentPath.value.startsWith(`${href}/`)
    );
};

const openNav = () => {
    navOpen.value = true;
};

const closeNav = () => {
    navOpen.value = false;
};

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

/** Shared classes: persistent sidebar lg+, off-canvas drawer below lg */
const railClass = computed(() => {
    const open = navOpen.value
        ? 'max-lg:translate-x-0'
        : 'max-lg:-translate-x-full';

    return [
        'fixed inset-y-0 left-0 z-50 flex w-[min(18rem,85vw)] flex-col overflow-x-hidden border-r border-line bg-ground shadow-lg transition-[transform,width] duration-200 ease-in-out',
        'lg:fixed lg:top-0 lg:z-40 lg:h-screen lg:translate-x-0 lg:shadow-none',
        sidebarCompact.value && !props.settingsNav ? 'lg:w-16' : 'lg:w-56',
        open,
    ].join(' ');
});
</script>

<template>
    <Head :title="title" />

    <div
        class="flex min-h-screen bg-page text-charcoal antialiased"
        :class="
            sidebarCollapsed && !settingsNav
                ? 'lg:[--app-sidebar-width:4rem]'
                : 'lg:[--app-sidebar-width:14rem]'
        "
    >
        <!-- Scrim (below lg only) -->
        <div
            class="fixed inset-0 z-40 bg-charcoal/40 transition-opacity lg:hidden"
            :class="
                navOpen
                    ? 'pointer-events-auto opacity-100'
                    : 'pointer-events-none opacity-0'
            "
            aria-hidden="true"
            @click="closeNav"
        />

        <!-- Main app rail (single outlet) -->
        <aside
            v-if="!settingsNav"
            :class="railClass"
            :aria-label="$t('nav.sidebar')"
            :aria-hidden="navOpen || undefined"
            @mouseenter="sidebarHovered = true"
            @mouseleave="sidebarHovered = false"
        >
            <div
                class="flex shrink-0 items-center justify-between border-b border-line px-4 py-3 transition-[padding] duration-200 ease-in-out lg:h-16 lg:py-0"
                :class="sidebarCompact ? 'lg:justify-center lg:px-2' : ''"
            >
                <div
                    class="flex min-w-0 items-center gap-2.5 transition-[gap] duration-200 ease-in-out"
                    :class="sidebarCompact ? 'lg:gap-0' : ''"
                >
                    <div
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-bold text-white"
                        aria-hidden="true"
                    >
                        {{ $t('app.mark') }}
                    </div>
                    <div
                        class="min-w-0 overflow-hidden transition-[max-width,opacity] duration-150 ease-out"
                        :class="
                            sidebarCompact
                                ? 'lg:max-w-0 lg:opacity-0'
                                : 'lg:max-w-48 lg:opacity-100'
                        "
                    >
                        <span class="block truncate text-[15px] font-bold">
                            {{ $t('app.name') }}
                        </span>
                        <span
                            v-if="eventName"
                            class="mt-0.5 block truncate text-xs text-muted"
                        >
                            {{ eventName }}
                        </span>
                        <span
                            v-else-if="organizationName"
                            class="mt-0.5 block truncate text-xs text-muted"
                        >
                            {{ organizationName }}
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-charcoal hover:bg-page lg:hidden"
                    :aria-label="$t('nav.close_menu')"
                    @click="closeNav"
                >
                    <Icon
                        :name="['fas', 'xmark']"
                        size="sm"
                    />
                </button>
            </div>

            <nav
                data-unsaved-navigation
                class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-2 py-3"
            >
                <template
                    v-for="item in navItems"
                    :key="item.key"
                >
                    <div
                        v-if="item.children"
                        class="space-y-1"
                    >
                        <SidebarNavItem
                            :href="
                                item.enabled && item.href
                                    ? item.href
                                    : undefined
                            "
                            :enabled="item.enabled"
                            :active="
                                isActive(item.href) ||
                                item.children.some((child) =>
                                    isActive(child.href),
                                )
                            "
                            :icon="item.icon"
                            :icon-only="sidebarCompact"
                            :aria-label="
                                sidebarCompact
                                    ? $t(`nav.${item.key}`)
                                    : undefined
                            "
                            :title="
                                sidebarCompact
                                    ? $t(`nav.${item.key}`)
                                    : undefined
                            "
                            :aria-current="
                                item.enabled &&
                                (isActive(item.href) ||
                                    item.children.some((child) =>
                                        isActive(child.href),
                                    ))
                                    ? 'page'
                                    : undefined
                            "
                        >
                            {{ $t(`nav.${item.key}`) }}
                        </SidebarNavItem>
                        <div
                            class="ml-5 space-y-1 border-l border-line pl-3 transition-[margin,padding,border-color] duration-200 ease-in-out"
                            :class="
                                sidebarCompact
                                    ? 'lg:ml-0 lg:border-transparent lg:pl-0'
                                    : ''
                            "
                        >
                            <SidebarNavItem
                                v-for="child in item.children"
                                :key="child.key"
                                :href="child.enabled ? child.href : undefined"
                                :enabled="child.enabled"
                                :active="isActive(child.href)"
                                :icon="sidebarCompact ? child.icon : null"
                                :icon-desktop-only="sidebarCompact"
                                :icon-only="sidebarCompact"
                                density="sub"
                                :aria-label="
                                    sidebarCompact
                                        ? $t(`nav.${child.key}`)
                                        : undefined
                                "
                                :title="
                                    sidebarCompact
                                        ? $t(`nav.${child.key}`)
                                        : undefined
                                "
                                :aria-current="
                                    child.enabled && isActive(child.href)
                                        ? 'page'
                                        : undefined
                                "
                            >
                                {{ $t(`nav.${child.key}`) }}
                            </SidebarNavItem>
                        </div>
                    </div>
                    <SidebarNavItem
                        v-else
                        :href="item.enabled ? item.href : undefined"
                        :enabled="item.enabled"
                        :active="isActive(item.href)"
                        :icon="item.icon"
                        :icon-only="sidebarCompact"
                        :aria-label="
                            sidebarCompact ? $t(`nav.${item.key}`) : undefined
                        "
                        :title="
                            sidebarCompact ? $t(`nav.${item.key}`) : undefined
                        "
                        :aria-current="
                            item.enabled && isActive(item.href)
                                ? 'page'
                                : undefined
                        "
                    >
                        {{ $t(`nav.${item.key}`) }}
                    </SidebarNavItem>
                </template>
                <SidebarNavItem
                    v-if="user?.is_admin"
                    href="/settings/events"
                    :active="settingsActive"
                    :icon="['fas', 'gear']"
                    :icon-only="sidebarCompact"
                    :aria-label="
                        sidebarCompact ? $t('nav.settings') : undefined
                    "
                    :title="sidebarCompact ? $t('nav.settings') : undefined"
                    :aria-current="settingsActive ? 'page' : undefined"
                >
                    {{ $t('nav.settings') }}
                </SidebarNavItem>
            </nav>

            <SidebarCollapseButton
                :collapsed="sidebarCollapsed"
                :compact="sidebarCompact"
                @toggle="toggleSidebar"
            />
            <UserAccountControls
                :user="user"
                placement="sidebar"
            />
        </aside>

        <!-- Settings rail (single #settings-nav outlet) -->
        <aside
            v-if="settingsNav"
            :class="railClass"
            :aria-label="$t('settings.nav.label')"
        >
            <div
                class="flex items-center justify-between border-b border-line px-4 py-3 lg:hidden"
            >
                <span class="truncate text-[15px] font-bold">
                    {{ $t('nav.settings') }}
                </span>
                <button
                    type="button"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-charcoal hover:bg-page"
                    :aria-label="$t('nav.close_menu')"
                    @click="closeNav"
                >
                    <Icon
                        :name="['fas', 'xmark']"
                        size="sm"
                    />
                </button>
            </div>
            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
                <slot name="settings-nav" />
            </div>
            <UserAccountControls
                :user="user"
                placement="sidebar"
            />
        </aside>

        <div
            class="flex min-w-0 flex-1 flex-col transition-[margin] duration-200"
            :class="sidebarCollapsed && !settingsNav ? 'lg:ml-16' : 'lg:ml-56'"
        >
            <header
                class="flex h-14 shrink-0 items-center gap-3 border-b border-line bg-ground px-4 md:px-6"
            >
                <button
                    type="button"
                    class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg text-charcoal hover:bg-page lg:hidden"
                    :aria-label="$t('nav.menu')"
                    :aria-expanded="navOpen ? 'true' : 'false'"
                    @click="openNav"
                >
                    <Icon
                        :name="['fas', 'bars']"
                        size="sm"
                    />
                </button>
                <Link
                    v-if="backHref"
                    :href="backHref"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] text-charcoal no-underline hover:bg-page"
                    :aria-label="backLabel || $t('nav.back')"
                    :title="backLabel || $t('nav.back')"
                >
                    <Icon
                        :name="['fas', 'arrow-left']"
                        size="sm"
                    />
                </Link>
                <div
                    class="flex min-w-0 flex-1 items-center justify-between gap-3 lg:hidden"
                    :class="backHref ? 'hidden' : ''"
                >
                    <span class="truncate text-[15px] font-bold">
                        {{ $t('app.name') }}
                    </span>
                    <span
                        v-if="eventName"
                        class="max-w-[45%] shrink-0 truncate text-sm text-muted"
                    >
                        {{ eventName }}
                    </span>
                </div>
                <nav
                    data-unsaved-navigation
                    class="hidden min-w-0 flex-1 items-center gap-2 overflow-hidden text-sm text-muted lg:flex"
                    :class="
                        backHref ? 'ml-5 border-l border-line pl-5 lg:flex' : ''
                    "
                    :aria-label="$t('nav.breadcrumbs')"
                >
                    <template
                        v-for="(crumb, index) in crumbItems"
                        :key="`${crumb.label}-${index}`"
                    >
                        <span
                            v-if="index > 0"
                            class="hidden text-line sm:inline"
                            aria-hidden="true"
                        >
                            /
                        </span>
                        <Link
                            v-if="crumb.href"
                            :href="crumb.href"
                            class="hidden truncate font-medium text-muted no-underline hover:text-charcoal sm:inline"
                        >
                            {{ crumb.label }}
                        </Link>
                        <span
                            v-else
                            class="truncate font-semibold text-charcoal"
                            aria-current="page"
                        >
                            {{ crumb.label }}
                        </span>
                    </template>
                </nav>
                <nav
                    v-if="backHref"
                    data-unsaved-navigation
                    class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden border-l border-line pl-3 text-sm text-muted lg:hidden"
                    :aria-label="$t('nav.breadcrumbs')"
                >
                    <template
                        v-for="(crumb, index) in crumbItems"
                        :key="`m-${crumb.label}-${index}`"
                    >
                        <span
                            v-if="index > 0"
                            class="text-line"
                            aria-hidden="true"
                        >
                            /
                        </span>
                        <Link
                            v-if="crumb.href"
                            :href="crumb.href"
                            class="truncate font-medium text-muted no-underline hover:text-charcoal"
                        >
                            {{ crumb.label }}
                        </Link>
                        <span
                            v-else
                            class="truncate font-semibold text-charcoal"
                            aria-current="page"
                        >
                            {{ crumb.label }}
                        </span>
                    </template>
                </nav>
                <UserAccountControls :user="user" />
            </header>

            <main class="flex-1 px-4 py-6 md:px-6 md:py-8">
                <div class="container mx-auto">
                    <slot />
                </div>
            </main>
        </div>

        <Toast />
    </div>
</template>
