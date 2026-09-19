<script setup>
import { Toast } from '../components/ui/toast';
import { Icon } from '../components/ui/icon';
import SidebarNavItem from '../components/navigation/SidebarNavItem.vue';
import { useInertiaErrorToast } from '../composables/useInertiaErrorToast';
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

const navItems = computed(() => [
    {
        key: 'home',
        href: '/dashboard',
        icon: ['fas', 'house'],
        enabled: true,
    },
    {
        key: 'artists',
        href: '/artists/advancing',
        icon: ['fas', 'music'],
        enabled: true,
        children: [
            {
                key: 'artists.advancing',
                href: '/artists/advancing',
                enabled: true,
            },
            {
                key: 'artists.check_in',
                href: '/artists/check-in',
                enabled: true,
            },
        ],
    },
    {
        key: 'vendors',
        href: null,
        icon: ['fas', 'store'],
        enabled: true,
        children: [
            {
                key: 'vendors.advancing',
                href: '/vendors/advancing',
                enabled: true,
            },
            {
                key: 'vendors.check_in',
                href: '/vendors/check-in',
                enabled: true,
            },
        ],
    },
    {
        key: 'patrons',
        href: '/patrons',
        icon: ['fas', 'address-book'],
        enabled: features.value.patrons ?? true,
    },
    {
        key: 'crew',
        href: '/crew',
        icon: ['fas', 'users'],
        enabled: features.value.crew ?? true,
    },
]);

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

    return (
        currentPath.value === href || currentPath.value.startsWith(`${href}/`)
    );
};

const signOutClass =
    'inline-flex min-h-11 w-full items-center justify-start px-2 text-[13px] font-semibold text-primary no-underline hover:underline';

const openNav = () => {
    navOpen.value = true;
};

const closeNav = () => {
    navOpen.value = false;
};

/** Shared classes: persistent sidebar lg+, off-canvas drawer below lg */
const railClass = computed(() => {
    const open = navOpen.value
        ? 'max-lg:translate-x-0'
        : 'max-lg:-translate-x-full';

    return [
        'fixed inset-y-0 left-0 z-50 flex w-[min(18rem,85vw)] flex-col border-r border-line bg-ground shadow-lg transition-transform duration-200',
        'lg:static lg:z-auto lg:w-56 lg:shrink-0 lg:translate-x-0 lg:shadow-none',
        open,
    ].join(' ');
});
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen bg-page text-charcoal antialiased">
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
        >
            <div
                class="flex items-center justify-between border-b border-line px-4 py-3 lg:block lg:py-4"
            >
                <div class="flex min-w-0 items-center gap-2.5">
                    <div
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-bold text-white"
                        aria-hidden="true"
                    >
                        {{ $t('app.mark') }}
                    </div>
                    <div class="min-w-0">
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

            <nav class="flex flex-1 flex-col gap-1 overflow-y-auto px-2 py-3">
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
                        <div class="ml-5 border-l border-line pl-3">
                            <SidebarNavItem
                                v-for="child in item.children"
                                :key="child.key"
                                :href="child.enabled ? child.href : undefined"
                                :enabled="child.enabled"
                                :active="isActive(child.href)"
                                density="sub"
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
                        :aria-current="
                            item.enabled && isActive(item.href)
                                ? 'page'
                                : undefined
                        "
                    >
                        {{ $t(`nav.${item.key}`) }}
                    </SidebarNavItem>
                </template>
            </nav>

            <div class="mt-auto">
                <div class="border-t border-line px-2 py-2">
                    <SidebarNavItem
                        href="/settings/events"
                        :active="settingsActive"
                        :icon="['fas', 'gear']"
                        :aria-current="settingsActive ? 'page' : undefined"
                    >
                        {{ $t('nav.settings') }}
                    </SidebarNavItem>
                </div>
                <div class="space-y-1 border-t border-line px-3 py-3">
                    <div class="min-w-0 px-2">
                        <p
                            class="m-0 truncate text-xs font-semibold text-charcoal"
                        >
                            {{
                                user?.name ||
                                user?.email ||
                                $t('dashboard.unknown')
                            }}
                        </p>
                        <p
                            v-if="user?.email && user?.name"
                            class="m-0 truncate text-[11px] text-muted"
                        >
                            {{ user.email }}
                        </p>
                    </div>
                    <Link
                        method="post"
                        href="/logout"
                        as="button"
                        :class="signOutClass"
                    >
                        {{ $t('dashboard.sign_out') }}
                    </Link>
                </div>
            </div>
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
            <div class="mt-auto space-y-1 border-t border-line px-3 py-3">
                <div class="min-w-0 px-2">
                    <p class="m-0 truncate text-xs font-semibold text-charcoal">
                        {{
                            user?.name || user?.email || $t('dashboard.unknown')
                        }}
                    </p>
                    <p
                        v-if="user?.email && user?.name"
                        class="m-0 truncate text-[11px] text-muted"
                    >
                        {{ user.email }}
                    </p>
                </div>
                <Link
                    method="post"
                    href="/logout"
                    as="button"
                    :class="signOutClass"
                >
                    {{ $t('dashboard.sign_out') }}
                </Link>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
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
            </header>

            <main class="flex-1 px-4 py-6 md:px-6 md:py-8">
                <div class="mx-auto w-full max-w-[1200px]">
                    <slot />
                </div>
            </main>
        </div>

        <Toast />
    </div>
</template>
