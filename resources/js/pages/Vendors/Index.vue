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
import { navigateDataTableRow } from '../../lib/dataTableRowNavigation';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { vendorColumns } from './vendorColumns';

const props = defineProps({
    vendors: { type: Object, required: true },
    vendorTypes: { type: Array, required: true },
    event: { type: Object, default: null },
});

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.vendors'), href: '/vendors/advancing' },
    { label: trans('vendors.title') },
]);
// DataTables reads columns and options once on mount; later changes are ignored.
const columns = computed(() => vendorColumns(trans));
const table = ref(null);
const search = ref('');
const selectedType = ref('');
const matchesVendor = (vendor, normalizedSearch) => {
    const matchesSearch =
        normalizedSearch === '' ||
        vendor.name.toLocaleLowerCase().includes(normalizedSearch) ||
        (vendor.type || '').toLocaleLowerCase().includes(normalizedSearch) ||
        trans(`vendors.status.${vendor.status}`)
            .toLocaleLowerCase()
            .includes(normalizedSearch);
    const matchesType =
        selectedType.value === '' ||
        vendor.vendor_type_id === selectedType.value;

    return matchesSearch && matchesType;
};
// Phone cards use the same order as the desktop table (name ascending).
const filteredVendors = computed(() => {
    const normalizedSearch = search.value.trim().toLocaleLowerCase();

    return props.vendors.data
        .filter((vendor) => matchesVendor(vendor, normalizedSearch))
        .sort((first, second) => first.name.localeCompare(second.name));
});
const typeOptions = computed(() => [
    {
        value: '',
        title: trans('vendors.filters.all_types'),
    },
    ...props.vendorTypes.map((type) => ({ value: type.id, title: type.name })),
]);
const options = computed(() => ({
    lengthChange: false,
    layout: {
        topStart: null,
        topEnd: null,
        bottomEnd: null,
    },
    order: [[0, 'asc']],
    columnDefs: [{ targets: 3, className: 'text-right' }],
    createdRow: (row, vendor) =>
        navigateDataTableRow(
            row,
            vendor,
            (item) => `/vendors/engagements/${item.id}`,
        ),
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
    table.value?.filterRows(
        'vendorType',
        value === '' ? null : (vendor) => vendor.vendor_type_id === value,
    );
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
                    :disabled="
                        event.locked || !$page.props.permissions['vendors.edit']
                    "
                    :title="
                        !$page.props.permissions['vendors.edit']
                            ? $t('permissions.no_add.vendors')
                            : undefined
                    "
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
                            class="pointer-events-none absolute top-3.5 left-3 z-10 text-muted"
                            size="sm"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            class="min-h-11 pl-9"
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

                <div class="flex flex-col gap-3 md:hidden">
                    <div
                        v-for="vendor in filteredVendors"
                        :key="vendor.id"
                        class="rounded-xl border border-line bg-ground p-4"
                    >
                        <div class="flex items-center gap-3">
                            <Avatar
                                :name="vendor.name"
                                size="sm"
                            />
                            <Link
                                :href="`/vendors/engagements/${vendor.id}`"
                                class="font-semibold text-charcoal"
                            >
                                {{ vendor.name }}
                            </Link>
                            <Badge
                                variant="neutral"
                                pill
                            >
                                {{ $t(`vendors.status.${vendor.status}`) }}
                            </Badge>
                        </div>
                        <p
                            v-if="vendor.type"
                            class="mt-2 mb-0 text-sm text-muted"
                        >
                            {{ vendor.type }}
                        </p>
                    </div>
                    <p
                        v-if="!filteredVendors.length"
                        class="rounded-xl border border-line bg-ground px-4 py-16 text-center text-muted"
                    >
                        {{
                            $t(
                                search || selectedType
                                    ? 'vendors.no_matches'
                                    : 'vendors.empty',
                            )
                        }}
                    </p>
                </div>

                <div class="hidden md:block">
                    <DataTable
                        ref="table"
                        :columns="columns"
                        :data="vendors.data"
                        :options="options"
                    >
                        <template #vendorCell="{ rowData }">
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
                        <template #typeCell="{ cellData }">
                            <span class="text-muted">
                                {{ cellData || $t('data_table.empty_value') }}
                            </span>
                        </template>
                        <template #statusCell="{ cellData }">
                            <Badge
                                variant="neutral"
                                pill
                            >
                                {{ $t(`vendors.status.${cellData}`) }}
                            </Badge>
                        </template>
                        <template #openCell="{ rowData }">
                            <Link
                                :href="`/vendors/engagements/${rowData.id}`"
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
                        </template>
                    </DataTable>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
