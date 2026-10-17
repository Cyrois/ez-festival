<script setup>
import { useReturnNavigation } from '../../composables/useReturnNavigation';
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import { BackLink } from '../ui/back-link';
import { Tabs, TabList, Tab } from '../ui/tabs';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const { returnUrl } = useReturnNavigation();

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
    form: { type: Object, default: null },
});
const emit = defineEmits(['save']);

const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.is_admin === true);
const showPrimaryBanner = computed(
    () => isAdmin.value && (props.forPrimary || props.event.is_active),
);

const breadcrumbs = computed(() => [
    {
        label: trans('app.name'),
        href: '/dashboard',
    },
    {
        label: trans('nav.settings'),
        href: isAdmin.value ? '/settings/events' : '/settings/locations',
    },
    ...(isAdmin.value
        ? [{ label: trans('settings.events.title'), href: '/settings/events' }]
        : []),
    {
        label: props.event.name,
    },
]);

const tabRoutes = computed(() => ({
    details: `/settings/events/${props.event.id}/edit`,
    locations: props.forPrimary
        ? '/settings/locations'
        : `/settings/events/${props.event.id}/locations`,
}));

const onTabChange = (value) => {
    const href = tabRoutes.value[value];
    if (!href || value === props.tab) {
        return;
    }
    router.get(returnUrl(href), {}, { preserveScroll: true });
};
</script>

<template>
    <SettingsLayout
        :title="event.name"
        :breadcrumbs="breadcrumbs"
    >
        <div class="mb-2">
            <BackLink
                v-if="isAdmin"
                href="/settings/events"
                :page="$t('settings.events.title')"
                restrict-context
                :form="form"
                @save="emit('save', $event)"
            />
            <h1 class="m-0 text-2xl font-bold tracking-tight">
                {{ event.name }}
            </h1>
            <p class="mt-1 mb-0 text-sm text-muted">
                {{
                    isAdmin
                        ? $t('settings.events.edit_lead')
                        : $t('settings.locations.current_event', {
                              name: event.name,
                          })
                }}
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
                {{ $t('settings.events.primary_banner_note_before') }}
                <Link
                    href="/settings/events"
                    class="mx-1 font-semibold text-primary no-underline hover:underline"
                >
                    {{ $t('settings.nav.items.events') }}
                </Link>
                {{ $t('settings.events.primary_banner_note_after') }}
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
            v-if="isAdmin"
            class="mb-6"
            :model-value="tab"
            @update:model-value="onTabChange"
        >
            <TabList class="flex-nowrap overflow-x-auto">
                <Tab value="details">
                    {{ $t('settings.events.tabs.details') }}
                </Tab>
                <Tab value="locations">
                    {{ $t('settings.events.tabs.locations') }}
                </Tab>
            </TabList>
        </Tabs>

        <slot />
    </SettingsLayout>
</template>
