<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { DataTable } from '../../components/ui/data-table';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    vendors: { type: Object, required: true },
    event: { type: Object, default: null },
});

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.vendors'), href: '/vendors/advancing' },
    { label: trans('vendors.title') },
]);
const columns = computed(() => [
    {
        data: 'name',
        name: 'vendor',
        title: trans('vendors.columns.vendor'),
    },
    {
        data: 'type',
        defaultContent: '',
        name: 'type',
        title: trans('vendors.columns.type'),
    },
    {
        data: 'status',
        name: 'status',
        title: trans('vendors.columns.status'),
    },
]);
const table = ref(null);
const search = ref('');
const selectedType = ref('');
const typeOptions = computed(() => [
    {
        value: '',
        title: trans('vendors.filters.all_types'),
    },
    ...[
        ...new Set(
            props.vendors.data
                .map((vendor) => vendor.type)
                .filter((type) => type),
        ),
    ]
        .sort((first, second) => first.localeCompare(second))
        .map((type) => ({ value: type, title: type })),
]);
const options = computed(() => ({
    lengthChange: false,
    layout: {
        topStart: null,
        topEnd: null,
        bottomEnd: null,
    },
    order: [[0, 'asc']],
    paging: false,
    language: {
        emptyTable: trans('vendors.empty'),
        searchPlaceholder: trans('vendors.search'),
        zeroRecords: trans('vendors.no_matches'),
    },
}));

watch(search, (value) => {
    table.value?.search(value);
});

watch(selectedType, (value) => {
    table.value?.searchColumn('type:name', value);
});
</script>

<template>
    <AppLayout
        :title="$t('vendors.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ $t('vendors.title') }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('vendors.lead') }}
                    </p>
                </div>
                <Button
                    v-if="event && !event.locked"
                    href="/vendors/create"
                    class="min-h-11 w-full sm:w-auto"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                    />
                    {{ $t('vendors.add') }}
                </Button>
            </div>

            <EmptyState
                v-if="!event"
                :title="$t('vendors.no_event.title')"
                :description="$t('vendors.no_event.body')"
            >
                <Button href="/settings/events">
                    {{ $t('settings.events.title') }}
                </Button>
            </EmptyState>

            <template v-else>
                <p
                    v-if="event.locked"
                    class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                    role="status"
                >
                    <Icon :name="['fas', 'lock']" />
                    {{ $t('vendors.locked') }}
                </p>

                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <form
                        class="relative w-full sm:w-64"
                        role="search"
                        @submit.prevent
                    >
                        <Icon
                            :name="['fas', 'magnifying-glass']"
                            class="pointer-events-none absolute top-3 left-3 z-10 text-muted"
                            size="sm"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            class="pl-9"
                            :aria-label="$t('vendors.search')"
                            :placeholder="$t('vendors.search')"
                            maxlength="255"
                        />
                    </form>
                    <div class="w-full sm:w-56">
                        <CustomDropdown
                            v-model="selectedType"
                            :items="typeOptions"
                            :placeholder="$t('vendors.filters.all_types')"
                            :empty-text="$t('vendors.filters.no_types')"
                            :aria-label="$t('vendors.filters.type')"
                        />
                    </div>
                </div>

                <DataTable
                    ref="table"
                    :columns="columns"
                    :data="vendors.data"
                    :options="options"
                >
                    <template #column-vendor="{ rowData }">
                        <Link
                            :href="`/vendors/engagements/${rowData.id}`"
                            class="flex items-center gap-3 font-semibold text-charcoal no-underline hover:text-primary hover:underline"
                        >
                            <Avatar
                                :name="rowData.name"
                                size="sm"
                            />
                            {{ rowData.name }}
                        </Link>
                    </template>
                    <template #column-type="{ cellData }">
                        <span class="text-muted">
                            {{ cellData || $t('vendors.not_set') }}
                        </span>
                    </template>
                    <template #column-status="{ cellData }">
                        <Badge
                            variant="neutral"
                            pill
                        >
                            {{ $t(`vendors.status.${cellData}`) }}
                        </Badge>
                    </template>
                </DataTable>
            </template>
        </div>
    </AppLayout>
</template>
