<script setup>
import AppLayout from './AppLayout.vue';
import { Icon } from '../components/ui/icon';
import { Link, usePage } from '@inertiajs/vue3';
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
    { key: 'general', enabled: false },
    { key: 'people', enabled: false },
    { key: 'artist_types', href: '/settings/artist-types', enabled: true },
    { key: 'vendor_types', href: '/settings/vendor-types', enabled: true },
    { key: 'custom_fields', enabled: false },
    { key: 'labels', enabled: false },
];

const eventNavItems = [
    { key: 'events', href: '/settings/events', match: 'events' },
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
        (key === 'artist_types' && path === '/settings/artist-types') ||
        (key === 'vendor_types' && path === '/settings/vendor-types')
    );
};

const itemClass = (active, enabled) => {
    if (!enabled) {
        return 'cursor-default text-charcoal/35';
    }

    if (active) {
        return 'bg-primary/10 text-primary';
    }

    return 'text-charcoal/80 hover:bg-page hover:text-charcoal';
};
</script>

<template>
    <AppLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        settings-nav
    >
        <template #settings-nav>
            <div class="flex flex-1 flex-col gap-5 px-3 py-4">
                <Link
                    href="/dashboard"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg px-2 py-2.5 text-[13px] font-semibold text-charcoal/80 no-underline hover:bg-page hover:text-charcoal"
                >
                    <Icon
                        :name="['fas', 'arrow-left']"
                        size="sm"
                        fixed-width
                    />
                    {{ $t('settings.nav.back') }}
                </Link>

                <div>
                    <p
                        class="m-0 mb-2 px-2 text-[11px] font-bold tracking-wide text-muted uppercase"
                    >
                        {{ $t('settings.nav.organization') }}
                    </p>
                    <nav class="flex flex-col gap-0.5">
                        <component
                            :is="item.enabled ? Link : 'span'"
                            v-for="item in organizationItems"
                            :key="item.key"
                            class="min-h-11 rounded-lg px-2 py-2.5 text-[13px] font-semibold"
                            :class="
                                itemClass(
                                    isOrganizationNavActive(item.key),
                                    item.enabled,
                                )
                            "
                            :href="item.enabled ? item.href : undefined"
                            :aria-current="
                                isOrganizationNavActive(item.key)
                                    ? 'page'
                                    : undefined
                            "
                            :aria-disabled="item.enabled ? undefined : 'true'"
                        >
                            {{ $t(`settings.nav.items.${item.key}`) }}
                        </component>
                    </nav>
                </div>
                <div>
                    <p
                        class="m-0 mb-2 px-2 text-[11px] font-bold tracking-wide text-muted uppercase"
                    >
                        {{ $t('settings.nav.events_group') }}
                    </p>
                    <nav class="flex flex-col gap-0.5">
                        <Link
                            v-for="item in eventNavItems"
                            :key="item.key"
                            :href="item.href"
                            class="inline-flex min-h-11 items-center rounded-lg px-2 py-2.5 text-[13px] font-semibold no-underline"
                            :class="
                                itemClass(isEventNavActive(item.match), true)
                            "
                            :aria-current="
                                isEventNavActive(item.match)
                                    ? 'page'
                                    : undefined
                            "
                        >
                            {{ $t(`settings.nav.items.${item.key}`) }}
                        </Link>
                    </nav>
                </div>
            </div>
        </template>

        <slot />
    </AppLayout>
</template>
