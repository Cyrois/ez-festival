<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { DataTable } from '../../components/ui/data-table';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { LabelCombobox } from '../../components/ui/label-combobox';
import { Tag } from '../../components/ui/tag';
import { navigateDataTableRow } from '../../lib/dataTableRowNavigation';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { passColumns } from './passColumns';

const props = defineProps({
    passes: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
    canWrite: { type: Boolean, default: false },
});

const page = usePage();
const search = ref('');
const selectedLabelIds = ref([]);
const table = ref(null);
const eventName = computed(() => page.props.activeEvent?.name ?? '');
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.credentials'), href: '/credentials/passes' },
    { label: trans('credentials.passes.title') },
]);
const lead = computed(() =>
    trans('credentials.passes.lead', { event: eventName.value }),
);
const columns = computed(() => passColumns(trans));
const options = computed(() => ({
    lengthChange: false,
    pageLength: 25,
    order: [[0, 'asc']],
    layout: {
        topStart: null,
        topEnd: null,
    },
    columnDefs: [{ targets: 3, className: 'text-right' }],
    createdRow: (row, pass) =>
        navigateDataTableRow(
            row,
            pass,
            (item) => `/credentials/passes/${item.id}/edit`,
        ),
    language: {
        emptyTable: trans('credentials.passes.empty.description'),
        zeroRecords: trans('credentials.passes.empty.filtered'),
    },
}));

const usageFor = (pass) =>
    pass.max_assignments === null
        ? trans('credentials.passes.usage.unlimited', {
              assigned: pass.assigned_count,
          })
        : trans('credentials.passes.usage.limited', {
              assigned: pass.assigned_count,
              capacity: pass.max_assignments,
          });

const clearLabelFilters = () => {
    selectedLabelIds.value = [];
};

watch(search, (value) => table.value?.search(value));
watch(selectedLabelIds, (value) => {
    table.value?.filterRows(
        'labels',
        value.length === 0
            ? null
            : (pass) =>
                  value.every((labelId) =>
                      pass.labels.some((label) => label.id === labelId),
                  ),
    );
});
</script>

<template>
    <AppLayout
        :title="$t('credentials.passes.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ $t('credentials.passes.title') }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ lead }}
                    </p>
                </div>
                <Button
                    v-if="canWrite"
                    href="/credentials/passes/create"
                    variant="primary"
                    class="min-h-10"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                    />
                    {{ $t('credentials.passes.create') }}
                </Button>
            </div>

            <div class="mb-3 flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-[420px]">
                    <Icon
                        :name="['fas', 'magnifying-glass']"
                        size="sm"
                        class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"
                    />
                    <label
                        class="sr-only"
                        for="pass-search"
                    >
                        {{ $t('credentials.passes.filters.search') }}
                    </label>
                    <Input
                        id="pass-search"
                        v-model="search"
                        type="search"
                        :placeholder="
                            $t('credentials.passes.filters.search_placeholder')
                        "
                        class="pl-9"
                    />
                </div>

                <div class="w-full sm:w-72">
                    <LabelCombobox
                        v-model="selectedLabelIds"
                        :labels="labels"
                        :placeholder="$t('credentials.passes.filters.labels')"
                        :aria-label="$t('credentials.passes.filters.labels')"
                    />
                    <p class="mt-1 mb-0 text-xs text-muted">
                        {{ $t('credentials.passes.filters.labels_hint') }}
                    </p>
                </div>
                <Button
                    v-if="selectedLabelIds.length"
                    type="button"
                    variant="ghost"
                    @click="clearLabelFilters"
                >
                    {{ $t('credentials.passes.filters.clear') }}
                </Button>
            </div>

            <DataTable
                ref="table"
                :columns="columns"
                :data="passes"
                :options="options"
            >
                <template #usageCell="{ rowData }">
                    <span class="text-muted">
                        {{ usageFor(rowData) }}
                    </span>
                </template>
                <template #labelsCell="{ cellData }">
                    <div class="flex flex-wrap gap-1.5">
                        <Tag
                            v-for="label in cellData"
                            :key="label.id"
                            :name="label.name"
                            :color="label.color"
                        />
                        <span
                            v-if="!cellData.length"
                            class="text-muted"
                        >
                            {{ $t('data_table.empty_value') }}
                        </span>
                    </div>
                </template>
                <template #openCell="{ rowData }">
                    <Link
                        :href="`/credentials/passes/${rowData.id}/edit`"
                        class="inline-flex text-muted hover:text-primary"
                        :aria-label="
                            $t('data_table.open', { name: rowData.name })
                        "
                    >
                        <Icon
                            :name="['fas', 'chevron-right']"
                            size="sm"
                        />
                    </Link>
                </template>
            </DataTable>

            <p
                v-if="!canWrite"
                class="mt-3 mb-0 text-xs leading-5 text-muted"
            >
                {{ $t('credentials.passes.read_only') }}
            </p>

            <p class="mt-3 mb-0 text-xs leading-5 text-muted">
                {{ $t('credentials.passes.note') }}
            </p>
        </div>
    </AppLayout>
</template>
