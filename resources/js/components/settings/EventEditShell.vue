<script setup>
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import { Button } from '../ui/button';
import { Icon } from '../ui/icon';
import { Tabs, TabList, Tab } from '../ui/tabs';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    tab: {
        type: String,
        required: true,
    },
    forPrimary: {
        type: Boolean,
        default: false,
    },
});

const showPrimaryBanner = computed(
    () => props.forPrimary || props.event.is_active,
);

const breadcrumbs = computed(() => [
    {
        label: trans('app.name'),
        href: '/dashboard',
    },
    {
        label: trans('nav.settings'),
        href: '/settings/events',
    },
    {
        label: trans('settings.events.title'),
        href: '/settings/events',
    },
    {
        label: props.event.name,
    },
]);

const tabRoutes = computed(() => ({
    details: `/settings/events/${props.event.id}/edit`,
    locations: props.forPrimary
        ? '/settings/locations'
        : `/settings/events/${props.event.id}/locations`,
    roles: props.forPrimary
        ? '/settings/roles'
        : `/settings/events/${props.event.id}/roles`,
    users: props.forPrimary
        ? '/settings/users'
        : `/settings/events/${props.event.id}/users`,
}));

const onTabChange = (value) => {
    const href = tabRoutes.value[value];
    if (!href || value === props.tab) {
        return;
    }
    router.get(href, {}, { preserveScroll: true });
};
</script>

<template>
    <SettingsLayout
        :title="event.name"
        :breadcrumbs="breadcrumbs"
    >
        <div class="mb-2">
            <Button
                href="/settings/events"
                variant="ghost"
                size="sm"
                class="mb-3 px-0"
            >
                <Icon
                    :name="['fas', 'arrow-left']"
                    size="sm"
                    class="mr-1.5"
                />
                {{ $t('settings.events.back') }}
            </Button>
            <h1 class="m-0 text-2xl font-bold tracking-tight">
                {{ event.name }}
            </h1>
            <p class="mt-1 mb-0 text-sm text-muted">
                {{ $t('settings.events.edit_lead') }}
            </p>
        </div>

        <div
            v-if="showPrimaryBanner"
            class="mb-4 rounded-lg border border-primary/20 bg-primary/10 px-4 py-3 text-sm text-charcoal"
        >
            <p class="m-0 font-semibold">
                {{
                    $t('settings.events.primary_banner', {
                        name: event.name,
                    })
                }}
            </p>
            <p class="mt-1 mb-0 text-muted">
                {{ $t('settings.events.primary_banner_note') }}
                <Link
                    href="/settings/events"
                    class="font-semibold text-primary no-underline hover:underline"
                >
                    {{ $t('settings.nav.items.events') }}
                </Link>
            </p>
        </div>

        <div
            v-if="event.is_read_only"
            class="mb-4 rounded-lg border border-warning/20 bg-warning/10 px-4 py-3 text-sm font-semibold text-warning"
        >
            {{
                event.is_locked
                    ? $t('events.read_only_locked')
                    : $t('events.read_only_inactive')
            }}
        </div>

        <Tabs
            class="mb-6"
            :model-value="tab"
            @update:model-value="onTabChange"
        >
            <TabList>
                <Tab value="details">
                    {{ $t('settings.events.tabs.details') }}
                </Tab>
                <Tab value="locations">
                    {{ $t('settings.events.tabs.locations') }}
                </Tab>
                <Tab value="roles">
                    {{ $t('settings.events.tabs.roles') }}
                </Tab>
                <Tab value="users">
                    {{ $t('settings.events.tabs.users') }}
                </Tab>
            </TabList>
        </Tabs>

        <slot />
    </SettingsLayout>
</template>
