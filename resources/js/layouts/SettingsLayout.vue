<script setup>
import AppLayout from './AppLayout.vue';
import { Icon } from '../components/ui/icon';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    hideSubnav: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);

const organizationItems = [
    { key: 'general', enabled: false },
    { key: 'people', enabled: false },
    { key: 'artist_types', enabled: false },
    { key: 'vendor_types', enabled: false },
    { key: 'custom_fields', enabled: false },
    { key: 'labels', enabled: false },
];

const eventsHref = '/settings/events';

const eventsActive = computed(
    () =>
        currentPath.value === eventsHref ||
        currentPath.value.startsWith(`${eventsHref}/`),
);

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
        :settings-nav="!hideSubnav"
    >
        <template
            v-if="!hideSubnav"
            #settings-nav
        >
            <div class="flex flex-1 flex-col gap-5 px-3 py-4">
                <Link
                    href="/dashboard"
                    class="inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-[13px] font-semibold text-charcoal/80 no-underline hover:bg-page hover:text-charcoal"
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
                        <span
                            v-for="item in organizationItems"
                            :key="item.key"
                            class="rounded-lg px-2 py-1.5 text-[13px] font-semibold"
                            :class="itemClass(false, item.enabled)"
                            aria-disabled="true"
                        >
                            {{ $t(`settings.nav.items.${item.key}`) }}
                        </span>
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
                            :href="eventsHref"
                            class="rounded-lg px-2 py-1.5 text-[13px] font-semibold no-underline"
                            :class="itemClass(eventsActive, true)"
                            :aria-current="eventsActive ? 'page' : undefined"
                        >
                            {{ $t('settings.nav.items.events') }}
                        </Link>
                    </nav>
                </div>
            </div>
        </template>
        <slot />
    </AppLayout>
</template>
