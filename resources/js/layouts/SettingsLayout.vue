<script setup>
import AppLayout from './AppLayout.vue';
import SidebarNavItem from '../components/navigation/SidebarNavItem.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);

const organizationItems = [
    { key: 'events', href: '/settings/events', enabled: true },
    { key: 'team', href: '/settings/team', enabled: true },
    { key: 'artist_types', href: '/settings/artist-types', enabled: true },
    { key: 'vendor_types', href: '/settings/vendor-types', enabled: true },
    { key: 'custom_fields', href: '/settings/custom-fields', enabled: true },
];

const personalItems = [
    { key: 'account', href: '/settings/account', enabled: true },
];

const eventNavItems = [
    { key: 'locations', href: '/settings/locations', match: 'locations' },
    { key: 'roles', href: '/settings/roles', match: 'roles' },
    { key: 'users', href: '/settings/users', match: 'users' },
];

const isEventNavActive = (match) => {
    const path = currentPath.value;

    if (match === 'events') {
        return (
            path === '/settings/events' ||
            /^\/settings\/events\/\d+\/edit$/.test(path)
        );
    }

    if (match === 'locations') {
        return (
            path === '/settings/locations' ||
            /^\/settings\/events\/\d+\/locations$/.test(path)
        );
    }

    if (match === 'roles') {
        return (
            path === '/settings/roles' ||
            /^\/settings\/events\/\d+\/roles$/.test(path)
        );
    }

    if (match === 'users') {
        return (
            path === '/settings/users' ||
            /^\/settings\/events\/\d+\/users$/.test(path)
        );
    }

    return false;
};

const isOrganizationNavActive = (key) => {
    const path = currentPath.value;

    return (
        (key === 'events' && isEventNavActive('events')) ||
        (key === 'team' && path === '/settings/team') ||
        (key === 'artist_types' && path === '/settings/artist-types') ||
        (key === 'vendor_types' && path === '/settings/vendor-types') ||
        (key === 'custom_fields' && path === '/settings/custom-fields')
    );
};

const isPersonalNavActive = (key) =>
    key === 'account' && currentPath.value === '/settings/account';
</script>

<template>
    <AppLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        settings-nav
    >
        <template #settings-nav>
            <div class="flex flex-1 flex-col gap-5 px-3 py-4">
                <SidebarNavItem
                    href="/dashboard"
                    density="settings"
                    :icon="['fas', 'arrow-left']"
                >
                    {{ $t('settings.nav.back') }}
                </SidebarNavItem>

                <div>
                    <p
                        class="m-0 mb-2 px-2 text-[11px] font-bold tracking-wide text-muted uppercase"
                    >
                        {{ $t('settings.nav.organization') }}
                    </p>
                    <nav class="flex flex-col gap-0.5">
                        <SidebarNavItem
                            v-for="item in organizationItems"
                            :key="item.key"
                            :href="item.enabled ? item.href : undefined"
                            :enabled="item.enabled"
                            :active="isOrganizationNavActive(item.key)"
                            density="settings"
                            :aria-current="
                                isOrganizationNavActive(item.key)
                                    ? 'page'
                                    : undefined
                            "
                        >
                            {{ $t(`settings.nav.items.${item.key}`) }}
                        </SidebarNavItem>
                    </nav>
                </div>
                <div>
                    <p
                        class="m-0 mb-2 px-2 text-[11px] font-bold tracking-wide text-muted uppercase"
                    >
                        {{ $t('settings.nav.personal') }}
                    </p>
                    <nav class="flex flex-col gap-0.5">
                        <SidebarNavItem
                            v-for="item in personalItems"
                            :key="item.key"
                            :href="item.enabled ? item.href : undefined"
                            :enabled="item.enabled"
                            :active="isPersonalNavActive(item.key)"
                            density="settings"
                            :aria-current="
                                isPersonalNavActive(item.key)
                                    ? 'page'
                                    : undefined
                            "
                        >
                            {{ $t(`settings.nav.items.${item.key}`) }}
                        </SidebarNavItem>
                    </nav>
                </div>
                <div>
                    <p
                        class="m-0 mb-2 px-2 text-[11px] font-bold tracking-wide text-muted uppercase"
                    >
                        {{ $t('settings.nav.events_group') }}
                    </p>
                    <nav class="flex flex-col gap-0.5">
                        <SidebarNavItem
                            v-for="item in eventNavItems"
                            :key="item.key"
                            :href="item.href"
                            density="settings"
                            :active="isEventNavActive(item.match)"
                            :aria-current="
                                isEventNavActive(item.match)
                                    ? 'page'
                                    : undefined
                            "
                        >
                            {{ $t(`settings.nav.items.${item.key}`) }}
                        </SidebarNavItem>
                    </nav>
                </div>
            </div>
        </template>

        <slot />
    </AppLayout>
</template>
