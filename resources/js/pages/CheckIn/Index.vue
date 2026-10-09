<script setup>
import HiddenPersonalInfo from '../../components/people/HiddenPersonalInfo.vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Select } from '../../components/ui/select';
import { DataTable } from '../../components/ui/data-table';
import { navigateDataTableRow } from '../../lib/dataTableRowNavigation';
import { checkInQuery } from './filters';

const props = defineProps({
    passes: { type: Array, required: true },
    filters: { type: Object, required: true },
    event: { type: Object, required: true },
});

const type = ref(props.filters.type ?? 'all');
const pass = ref(props.filters.pass ? String(props.filters.pass) : '');
const status = ref(props.filters.status ?? 'all');
const search = ref(props.filters.search ?? '');
const types = ['all', 'artist', 'vendor', 'patron', 'team'];
const statuses = ['all', 'not_started', 'partial', 'complete'];
const statusVariants = {
    not_started: 'neutral',
    partial: 'warning',
    complete: 'success',
};
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('check_in.title') },
]);
const checkInHref = (person) =>
    `/check-in/${person.type}s/${person.engagement_id}?person=${person.person_id}`;
const columns = computed(() => [
    {
        data: 'name',
        title: trans('check_in.columns.person'),
        render: { display: '#personCell' },
    },
    { data: 'pass_name', title: trans('check_in.columns.pass') },
    { data: 'context', title: trans('check_in.columns.context') },
    {
        data: 'check_in_status',
        title: trans('check_in.columns.status'),
        render: { display: '#statusCell' },
    },
    {
        data: null,
        title: '',
        defaultContent: '',
        orderable: false,
        searchable: false,
        render: { display: '#openCell' },
    },
]);
const tableOptions = computed(() => ({
    serverSide: true,
    ordering: false,
    lengthChange: false,
    pageLength: 25,
    layout: { topStart: null, topEnd: null },
    columnDefs: [{ targets: 4, className: 'text-right', width: '1%' }],
    createdRow: (row, person) => navigateDataTableRow(row, person, checkInHref),
    language: {
        emptyTable: trans('check_in.empty'),
        zeroRecords: trans('check_in.empty'),
    },
}));
const tableAjax = {
    url: '/check-in/data',
    data: (data) => {
        Object.assign(
            data,
            checkInQuery({
                type: type.value,
                pass: pass.value,
                status: status.value,
                search: search.value,
            }),
        );
        // DataTables serializes undefined as the literal string "undefined".
        // An empty pass must reach Laravel as null for nullable validation.
        data.pass = pass.value || null;
        data.search = search.value.trim();
    },
};
let searchTimer;

const applyFilters = () => {
    router.get(
        '/check-in',
        checkInQuery({
            type: type.value,
            pass: pass.value,
            status: status.value,
            search: search.value,
        }),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const scanQr = () => {
    const code = window.prompt(trans('check_in.scan_prompt'));

    if (code === null) return;
    search.value = code;
};

watch([type, pass, status], applyFilters);
watch(search, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(applyFilters, 300);
});
onBeforeUnmount(() => window.clearTimeout(searchTimer));
</script>

<template>
    <AppLayout
        :title="$t('check_in.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <h1 class="m-0 text-2xl font-bold tracking-tight">
                {{ $t('check_in.title') }}
            </h1>
            <p class="mt-1 mb-0 text-sm text-muted">
                {{ $t('check_in.lead') }}
            </p>
            <p class="mt-1 mb-5 text-xs text-muted/70">
                {{ $t('check_in.shift_note') }}
            </p>

            <div class="mb-3 flex flex-wrap items-center gap-2">
                <span class="mr-1 text-xs font-bold text-muted">
                    {{ $t('check_in.filters.type') }}
                </span>
                <Button
                    v-for="value in types"
                    :key="value"
                    size="sm"
                    :variant="type === value ? 'outline-secondary' : 'outline'"
                    class="rounded-full font-normal"
                    @click="type = value"
                >
                    {{ $t(`check_in.types.${value}`) }}
                </Button>
                <label class="ml-2 text-xs font-bold text-muted">
                    {{ $t('check_in.filters.pass') }}
                </label>
                <div class="w-44">
                    <Select
                        v-model="pass"
                        :aria-label="$t('check_in.filters.pass')"
                    >
                        <option value="">
                            {{ $t('check_in.filters.all_passes') }}
                        </option>
                        <option
                            v-for="passType in passes"
                            :key="passType.id"
                            :value="passType.id"
                        >
                            {{ passType.name }}
                        </option>
                    </Select>
                </div>
            </div>

            <div class="mb-3 flex flex-wrap items-center gap-2">
                <form
                    class="relative min-w-64 flex-1"
                    @submit.prevent="applyFilters"
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
                        :placeholder="$t('check_in.filters.search_placeholder')"
                        :aria-label="$t('check_in.filters.search_placeholder')"
                    />
                </form>
                <Button
                    variant="outline"
                    @click="scanQr"
                >
                    <Icon :name="['fas', 'qrcode']" />
                    {{ $t('check_in.filters.scan_qr') }}
                </Button>
                <Button
                    v-for="value in statuses"
                    :key="value"
                    size="sm"
                    :variant="
                        status === value ? 'outline-secondary' : 'outline'
                    "
                    class="rounded-full font-normal"
                    @click="status = value"
                >
                    {{ $t(`check_in.status.${value}`) }}
                </Button>
            </div>

            <p class="mt-0 mb-4 text-xs text-muted">
                {{ $t('check_in.results_note') }}
            </p>

            <DataTable
                :key="JSON.stringify(filters)"
                :ajax="tableAjax"
                :columns="columns"
                :options="tableOptions"
            >
                <template #personCell="{ rowData }">
                    <div class="flex min-w-44 items-center gap-2.5">
                        <Avatar
                            :name="rowData.name"
                            size="sm"
                        />
                        <div>
                            <Link
                                :href="checkInHref(rowData)"
                                class="block font-bold text-charcoal no-underline hover:text-primary"
                            >
                                {{ rowData.name }}
                            </Link>
                            <span class="block text-xs text-muted">
                                <HiddenPersonalInfo
                                    v-if="rowData.personal_info_hidden"
                                />
                                <span v-else>{{ rowData.subtitle }}</span>
                            </span>
                        </div>
                    </div>
                </template>
                <template #statusCell="{ rowData }">
                    <Badge
                        :variant="statusVariants[rowData.check_in_status]"
                        pill
                        class="uppercase"
                    >
                        {{ $t(`check_in.status.${rowData.check_in_status}`) }}
                    </Badge>
                </template>
                <template #openCell="{ rowData }">
                    <Link
                        :href="checkInHref(rowData)"
                        class="inline-flex text-muted hover:text-primary"
                        :aria-label="
                            rowData.check_in_status === 'complete'
                                ? $t('check_in.actions.view')
                                : $t('check_in.actions.check_in')
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
    </AppLayout>
</template>
