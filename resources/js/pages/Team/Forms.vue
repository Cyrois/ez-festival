<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { DataTable } from '../../components/ui/data-table';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { SegmentedControl } from '../../components/ui/segmented-control';
import { useFlashToast } from '../../composables/useFlashToast';
import { navigateDataTableRow } from '../../lib/dataTableRowNavigation';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { formColumns } from './formColumns';

const props = defineProps({
    forms: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    event: { type: Object, default: null },
    canWrite: { type: Boolean, default: false },
});

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.forms') },
]);
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const { showSuccess } = useFlashToast();
let searchTimer;
const columns = computed(() => formColumns(trans));
const tableOptions = computed(() => ({
    searching: false,
    ordering: false,
    paging: false,
    info: false,
    layout: {
        topStart: null,
        topEnd: null,
        bottomStart: null,
        bottomEnd: null,
    },
    columnDefs: [{ targets: 3, className: 'text-right' }],
    createdRow: (row, teamForm) =>
        navigateDataTableRow(row, teamForm, (item) => item.edit_url),
}));

const filterOptions = computed(() => [
    { value: '', label: trans('team.forms.filters.all') },
    ...props.statuses.map((value) => ({
        value,
        label: trans(`team.forms.status.${value}`),
    })),
]);

const applyFilters = () => {
    router.get(
        '/team/forms',
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(search, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(applyFilters, 250);
});
watch(status, applyFilters);

const copyLink = async (url) => {
    await navigator.clipboard.writeText(url);
    showSuccess(trans('team.forms.toast.link_copied'));
};
</script>

<template>
    <AppLayout
        :title="$t('team.forms.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto flex flex-col gap-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ $t('team.forms.title') }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('team.forms.lead') }}
                    </p>
                </div>
                <Button
                    href="/team/forms/create"
                    :disabled="!canWrite"
                    :title="
                        !canWrite ? $t('permissions.no_add.forms') : undefined
                    "
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                        class="mr-2"
                    />
                    {{ $t('team.forms.actions.create') }}
                </Button>
            </div>

            <div class="flex flex-wrap gap-3">
                <div class="relative min-w-64 flex-1 sm:max-w-sm">
                    <Icon
                        :name="['fas', 'magnifying-glass']"
                        size="sm"
                        class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"
                    />
                    <Input
                        v-model="search"
                        class="pl-9"
                        :placeholder="$t('team.forms.filters.search')"
                    />
                </div>
                <SegmentedControl
                    v-model="status"
                    :options="filterOptions"
                    :aria-label="$t('team.forms.filters.status')"
                    variant="joined"
                />
            </div>

            <DataTable
                v-if="forms.data.length"
                :columns="columns"
                :data="forms.data"
                :options="tableOptions"
            >
                <template #statusCell="{ cellData }">
                    <Badge
                        pill
                        :variant="cellData === 'live' ? 'success' : 'warning'"
                    >
                        {{ $t(`team.forms.status.${cellData}`) }}
                    </Badge>
                </template>
                <template #publicLinkCell="{ rowData }">
                    <button
                        v-if="rowData.status === 'live'"
                        type="button"
                        class="max-w-72 truncate rounded-lg border border-line bg-page px-2 py-1 font-mono text-xs text-secondary hover:border-secondary"
                        :title="rowData.public_url"
                        @click="copyLink(rowData.public_url)"
                    >
                        {{ rowData.public_url }}
                    </button>
                    <span
                        v-else
                        class="inline-flex"
                    >
                        <Button
                            :href="rowData.preview_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            variant="ghost"
                            size="xs"
                        >
                            {{ $t('team.forms.actions.preview') }}
                        </Button>
                    </span>
                </template>
                <template #openCell="{ rowData }">
                    <Link
                        :href="rowData.edit_url"
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

            <EmptyState
                v-else
                :title="$t('team.forms.empty.title')"
                :description="
                    $t(
                        search || status
                            ? 'team.forms.empty.filtered'
                            : 'team.forms.empty.description',
                    )
                "
            >
                <Button
                    v-if="!search && !status"
                    href="/team/forms/create"
                    :disabled="!canWrite"
                    :title="
                        !canWrite ? $t('permissions.no_add.forms') : undefined
                    "
                >
                    {{ $t('team.forms.actions.create') }}
                </Button>
            </EmptyState>
        </div>
    </AppLayout>
</template>
