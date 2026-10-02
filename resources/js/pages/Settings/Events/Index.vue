<script setup>
import AppLayout from '../../../layouts/AppLayout.vue';
import SettingsLayout from '../../../layouts/SettingsLayout.vue';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { DataTable } from '../../../components/ui/data-table';
import { EmptyState } from '../../../components/ui/empty-state';
import { Icon } from '../../../components/ui/icon';
import { Input } from '../../../components/ui/input';
import { navigateDataTableRow } from '../../../lib/dataTableRowNavigation';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { eventColumns } from './eventColumns';

const props = defineProps({
    settings: { type: Boolean, default: true },
    events: {
        type: Array,
        default: () => [],
    },
});

const setPrimaryBusy = ref(false);
const search = ref('');
const table = ref(null);

const setPrimary = (event) => {
    if (event.is_active || setPrimaryBusy.value) {
        return;
    }
    setPrimaryBusy.value = true;
    router[props.settings ? 'post' : 'put'](
        props.settings
            ? `/settings/events/${event.id}/set-primary`
            : `/events/${event.id}/current`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                setPrimaryBusy.value = false;
            },
        },
    );
};

const breadcrumbs = computed(() =>
    [
        {
            label: trans('app.name'),
            href: '/dashboard',
        },
        {
            label: trans('nav.settings'),
            href: props.settings ? '/settings/events' : '/events',
        },
        {
            label: trans('settings.events.title'),
        },
    ].filter((item) => props.settings || item.label !== trans('nav.settings')),
);

const formatSubtext = (event) => {
    return `${event.starts_on} – ${event.ends_on}`;
};

const primaryStatus = (event) => {
    if (event.is_locked) {
        return 'locked';
    }
    if (event.is_active) {
        return 'active';
    }
    if (event.is_past) {
        return 'past';
    }
    return null;
};

const statusVariant = {
    active: 'success',
    locked: 'warning',
    past: 'neutral',
};
const filteredEvents = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();

    if (query === '') {
        return props.events;
    }

    return props.events.filter((event) =>
        [event.name, event.starts_on, event.ends_on]
            .join(' ')
            .toLocaleLowerCase()
            .includes(query),
    );
});
const columns = computed(() => eventColumns(trans));
const tableOptions = computed(() => ({
    lengthChange: false,
    pageLength: 25,
    order: [
        [1, 'desc'],
        [0, 'asc'],
    ],
    layout: {
        topStart: null,
        topEnd: null,
    },
    columnDefs: [{ targets: 4, className: 'text-right' }],
    createdRow: (row, event) =>
        navigateDataTableRow(row, event, (item) =>
            props.settings
                ? `/settings/events/${item.id}/edit`
                : `/events/${item.id}`,
        ),
    language: {
        zeroRecords: trans('settings.events.empty.filtered'),
    },
}));

watch(search, (value) => table.value?.search(value));
</script>

<template>
    <component
        :is="settings ? SettingsLayout : AppLayout"
        :title="$t('settings.events.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <div
                class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ $t('settings.events.title') }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('settings.events.lead') }}
                    </p>
                </div>
                <Button
                    v-if="settings"
                    href="/settings/events/create"
                    variant="primary"
                    class="min-h-11 w-full sm:w-auto"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                    />
                    {{ $t('settings.events.actions.create') }}
                </Button>
            </div>

            <EmptyState
                v-if="events.length === 0"
                :title="$t('events.empty.title')"
                :description="$t('events.empty.body')"
            >
                <template #icon>
                    <Icon
                        :name="['fas', 'calendar-days']"
                        size="lg"
                    />
                </template>
            </EmptyState>

            <template v-else>
                <form
                    class="relative mb-4 w-full sm:w-64"
                    role="search"
                    @submit.prevent
                >
                    <Icon
                        :name="['fas', 'magnifying-glass']"
                        class="pointer-events-none absolute top-3.5 left-3 z-10 text-muted"
                        size="sm"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        class="min-h-11 pl-9"
                        :aria-label="$t('settings.events.search')"
                        :placeholder="$t('settings.events.search')"
                    />
                </form>

                <!-- Phone: card stack -->
                <div class="flex flex-col gap-3 md:hidden">
                    <div
                        v-for="event in filteredEvents"
                        :key="`card-${event.id}`"
                        class="rounded-xl border border-line bg-ground p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-semibold">
                                    {{ event.name }}
                                </div>
                                <div class="mt-0.5 text-xs text-muted">
                                    {{ formatSubtext(event) }}
                                </div>
                            </div>
                            <Badge
                                v-if="primaryStatus(event)"
                                :variant="statusVariant[primaryStatus(event)]"
                                pill
                            >
                                {{
                                    $t(`events.status.${primaryStatus(event)}`)
                                }}
                            </Badge>
                        </div>
                        <div class="mt-4 flex flex-wrap justify-end gap-2">
                            <Button
                                v-if="!event.is_active"
                                variant="outline"
                                size="sm"
                                class="min-h-11"
                                :loading="setPrimaryBusy"
                                :disabled="setPrimaryBusy"
                                @click="setPrimary(event)"
                            >
                                <Icon
                                    :name="['fas', 'star']"
                                    size="sm"
                                    class="mr-1.5"
                                />
                                {{ $t('settings.events.actions.set_primary') }}
                            </Button>
                            <Button
                                v-if="settings && !event.is_locked"
                                :href="`/settings/events/${event.id}/edit`"
                                variant="primary"
                                size="sm"
                                class="min-h-11"
                            >
                                <Icon
                                    :name="['fas', 'pencil']"
                                    size="sm"
                                    class="mr-1.5"
                                />
                                {{ $t('settings.events.actions.edit') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- md+: table -->
                <div class="hidden md:block">
                    <DataTable
                        ref="table"
                        :columns="columns"
                        :data="events"
                        :options="tableOptions"
                    >
                        <template #eventCell="{ rowData }">
                            <Link
                                :href="
                                    settings
                                        ? `/settings/events/${rowData.id}/edit`
                                        : `/events/${rowData.id}`
                                "
                                class="font-semibold text-charcoal no-underline hover:text-primary hover:underline"
                            >
                                {{ rowData.name }}
                            </Link>
                        </template>
                        <template #statusCell="{ rowData }">
                            <Badge
                                v-if="primaryStatus(rowData)"
                                :variant="statusVariant[primaryStatus(rowData)]"
                                pill
                            >
                                {{
                                    $t(
                                        `events.status.${primaryStatus(rowData)}`,
                                    )
                                }}
                            </Badge>
                            <span
                                v-else
                                class="text-muted"
                            >
                                {{ $t('data_table.empty_value') }}
                            </span>
                        </template>
                        <template #actionsCell="{ rowData }">
                            <div class="flex items-center justify-end gap-2">
                                <Button
                                    v-if="!rowData.is_active"
                                    variant="outline"
                                    size="xs"
                                    :loading="setPrimaryBusy"
                                    :disabled="setPrimaryBusy"
                                    @click="setPrimary(rowData)"
                                >
                                    <Icon
                                        :name="['fas', 'star']"
                                        size="sm"
                                    />
                                    {{
                                        $t(
                                            'settings.events.actions.set_primary',
                                        )
                                    }}
                                </Button>
                                <Link
                                    :href="
                                        settings
                                            ? `/settings/events/${rowData.id}/edit`
                                            : `/events/${rowData.id}`
                                    "
                                    class="inline-flex text-muted hover:text-primary"
                                    :aria-label="
                                        $t('data_table.open', {
                                            name: rowData.name,
                                        })
                                    "
                                >
                                    <Icon
                                        :name="['fas', 'chevron-right']"
                                        size="sm"
                                    />
                                </Link>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </template>
        </div>
    </component>
</template>
