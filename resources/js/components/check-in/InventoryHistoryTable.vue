<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Button } from '../ui/button';
import { CustomDropdown } from '../ui/custom-dropdown';
import { DataTable } from '../ui/data-table';
import { EntitlementItemName } from '../ui/entitlement-labels';
import { FormField } from '../ui/form-field';
import { Input } from '../ui/input';

const props = defineProps({
    endpoint: { type: String, required: true },
    optionsEndpoint: { type: String, required: true },
    exportUrl: { type: String, default: null },
    timezone: { type: String, required: true },
    items: { type: Array, required: true },
    movementTypes: { type: Array, required: true },
    itemId: { type: Number, default: null },
});
const table = ref(null);
const pageLength = ref(10);
const filters = reactive({
    item_id: props.itemId ?? '',
    holder: '',
    recorded_by: '',
    type: '',
    start_date: '',
    end_date: '',
});
const applied = ref({ ...filters });
const errors = ref({});
const failed = ref(false);
let historyRequest;
let disposed = false;

// Each remote picker owns its pagination and cancellation; selected historical
// names remain available while searching another page of the catalogue.
const makePicker = (option, filterKey) => {
    const rows = ref([]);
    const selected = ref(null);
    const loading = ref(false);
    const hasMore = ref(false);
    const failed = ref(false);
    let page = 1;
    let search = '';
    let controller;
    let timer;
    const load = async (append = false) => {
        controller?.abort();
        const request = new AbortController();
        controller = request;
        loading.value = true;
        failed.value = false;
        const params = new URLSearchParams({
            option,
            page: String(page),
            search,
        });
        try {
            const response = await fetch(`${props.optionsEndpoint}?${params}`, {
                signal: request.signal,
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('options');
            const body = await response.json();
            if (disposed || request.signal.aborted) return;
            rows.value = append ? [...rows.value, ...body.data] : body.data;
            hasMore.value = Boolean(body.links?.next);
        } catch (error) {
            if (
                error.name !== 'AbortError' &&
                !disposed &&
                !request.signal.aborted
            )
                failed.value = true;
        } finally {
            if (controller === request) loading.value = false;
        }
    };
    const searchRows = (value) => {
        clearTimeout(timer);
        controller?.abort();
        search = value;
        page = 1;
        timer = setTimeout(() => load(), 200);
    };
    const options = computed(() => {
        const choices =
            selected.value &&
            !rows.value.some((row) => row.value === selected.value.value)
                ? [selected.value, ...rows.value]
                : rows.value;
        return [
            {
                value: '',
                title: trans(
                    option === 'holder'
                        ? 'inventory.history.all_holders'
                        : 'inventory.history.all_actors',
                ),
            },
            ...choices.map((row) => ({
                value: row.value,
                title: row.name,
                description:
                    row.kind === 'actor'
                        ? undefined
                        : trans(`inventory.columns.${row.kind}`),
            })),
        ];
    });
    onBeforeUnmount(() => {
        clearTimeout(timer);
        controller?.abort();
    });
    return {
        options,
        loading,
        hasMore,
        failed,
        search: searchRows,
        more: () => {
            page += 1;
            load(true);
        },
        retry: () => {
            page = 1;
            load();
        },
        select: (value) => {
            selected.value =
                rows.value.find((row) => row.value === value) ?? null;
            filters[filterKey] = value;
        },
    };
};
const holders = makePicker('holder', 'holder');
const actors = makePicker('actor', 'recorded_by');
onMounted(() => {
    holders.retry();
    actors.retry();
});
const itemOptions = computed(() => [
    ...(!props.itemId
        ? [{ value: '', title: trans('inventory.all_items') }]
        : []),
    ...props.items.map((item) => ({
        value: item.id,
        title: item.name,
        labels: item.labels ?? [],
    })),
]);
const typeOptions = computed(() => [
    { value: '', title: trans('inventory.history.all_types') },
    ...props.movementTypes.map((type) => ({
        value: type,
        title: trans(`inventory.movements.${type}`),
    })),
]);
const lengthOptions = [10, 20, 50].map((value) => ({
    value,
    title: String(value),
}));
const downloadUrl = computed(() => {
    if (!props.exportUrl) return null;
    const [path, query = ''] = props.exportUrl.split('?');
    const params = new URLSearchParams(query);
    Object.entries(applied.value).forEach(([key, value]) => {
        if (value !== '' && value !== null) params.set(key, String(value));
    });
    return `${path}?${params}`;
});
const applyFilters = () => {
    applied.value = { ...filters };
    errors.value = {};
    table.value?.reload();
};
const clearFilters = () => {
    Object.assign(filters, {
        item_id: props.itemId ?? '',
        holder: '',
        recorded_by: '',
        type: '',
        start_date: '',
        end_date: '',
    });
    applyFilters();
};
const changeLength = (value) => {
    pageLength.value = value;
    table.value?.setPageLength(value);
};
const ajax = async (data, callback) => {
    historyRequest?.abort();
    const request = new AbortController();
    historyRequest = request;
    failed.value = false;
    const params = new URLSearchParams({
        draw: String(data.draw),
        start: String(data.start),
        length: String(data.length),
        ...applied.value,
    });
    try {
        const response = await fetch(`${props.endpoint}?${params}`, {
            signal: request.signal,
            headers: { Accept: 'application/json' },
        });
        const body = await response.json();
        if (disposed || request.signal.aborted) return;
        if (!response.ok) {
            errors.value = Object.fromEntries(
                Object.entries(body.errors ?? {}).map(([key, messages]) => [
                    key,
                    messages[0],
                ]),
            );
            throw new Error('history');
        }
        errors.value = {};
        callback(body);
    } catch (error) {
        if (
            error.name !== 'AbortError' &&
            !disposed &&
            !request.signal.aborted
        ) {
            failed.value = true;
            callback({
                draw: data.draw,
                recordsTotal: 0,
                recordsFiltered: 0,
                data: [],
            });
        }
    }
};
onBeforeUnmount(() => {
    disposed = true;
    historyRequest?.abort();
});
const columns = computed(() => [
    {
        data: 'when',
        title: trans('inventory.columns.when'),
        render: { display: '#whenCell' },
    },
    {
        data: 'item',
        title: trans('inventory.columns.item'),
        render: { display: '#itemCell' },
    },
    {
        data: 'type',
        title: trans('inventory.columns.movement'),
        render: { display: '#typeCell' },
    },
    { data: 'quantity', title: trans('inventory.columns.quantity') },
    {
        data: 'giver',
        title: trans('inventory.columns.giver'),
        render: { display: '#giverCell' },
    },
    {
        data: 'receiver',
        title: trans('inventory.columns.receiver'),
        render: { display: '#receiverCell' },
    },
    {
        data: 'recorded_by',
        title: trans('inventory.columns.recorded_by'),
        render: { display: '#actorCell' },
    },
]);
const options = computed(() => ({
    serverSide: true,
    ordering: false,
    searching: false,
    lengthChange: false,
    pageLength: 10,
    layout: { topStart: null, topEnd: null },
    language: { emptyTable: trans('inventory.no_movements') },
}));
const formatWhen = (value) =>
    new Intl.DateTimeFormat(undefined, {
        timeZone: props.timezone,
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
</script>

<template>
    <div>
        <form
            class="mb-5 space-y-4"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <FormField
                    :label="$t('inventory.columns.item')"
                    :error="errors.item_id"
                >
                    <CustomDropdown
                        v-model="filters.item_id"
                        :items="itemOptions"
                        :disabled="itemId !== null"
                        :aria-label="$t('inventory.columns.item')"
                    />
                </FormField>
                <FormField
                    :label="$t('inventory.history.holder')"
                    :error="errors.holder"
                >
                    <CustomDropdown
                        :model-value="filters.holder"
                        :items="holders.options.value"
                        :loading="holders.loading.value"
                        :has-more="holders.hasMore.value"
                        :aria-label="$t('inventory.history.holder')"
                        remote
                        @update:model-value="holders.select"
                        @search="holders.search"
                        @load-more="holders.more"
                    />
                    <Button
                        v-if="holders.failed.value"
                        type="button"
                        variant="outline-secondary"
                        @click="holders.retry"
                        >{{ $t('inventory.history.retry_holders') }}</Button
                    >
                </FormField>
                <FormField
                    :label="$t('inventory.columns.recorded_by')"
                    :error="errors.recorded_by"
                >
                    <CustomDropdown
                        :model-value="filters.recorded_by"
                        :items="actors.options.value"
                        :loading="actors.loading.value"
                        :has-more="actors.hasMore.value"
                        :aria-label="$t('inventory.columns.recorded_by')"
                        remote
                        @update:model-value="actors.select"
                        @search="actors.search"
                        @load-more="actors.more"
                    />
                    <Button
                        v-if="actors.failed.value"
                        type="button"
                        variant="outline-secondary"
                        @click="actors.retry"
                        >{{ $t('inventory.history.retry_actors') }}</Button
                    >
                </FormField>
                <FormField
                    :label="$t('inventory.columns.movement')"
                    :error="errors.type"
                >
                    <CustomDropdown
                        v-model="filters.type"
                        :items="typeOptions"
                        :aria-label="$t('inventory.columns.movement')"
                    />
                </FormField>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <FormField
                    :label="$t('inventory.history.start_date')"
                    :error="errors.start_date"
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="filters.start_date"
                            type="date"
                            :invalid="invalid"
                        />
                    </template>
                </FormField>
                <FormField
                    :label="$t('inventory.history.end_date')"
                    :error="errors.end_date"
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="filters.end_date"
                            type="date"
                            :invalid="invalid"
                        />
                    </template>
                </FormField>
                <Button type="submit">{{
                    $t('inventory.history.apply')
                }}</Button>
                <Button
                    type="button"
                    variant="outline-secondary"
                    @click="clearFilters"
                    >{{ $t('inventory.history.clear') }}</Button
                >
            </div>
        </form>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <FormField :label="$t('inventory.history.page_size')">
                <CustomDropdown
                    :model-value="pageLength"
                    :items="lengthOptions"
                    :aria-label="$t('inventory.history.page_size')"
                    @update:model-value="changeLength"
                />
            </FormField>
            <Button
                v-if="downloadUrl && !failed"
                :href="downloadUrl"
                as="a"
                variant="outline-secondary"
                >{{ $t('inventory.history.export') }}</Button
            >
        </div>
        <div
            v-if="failed"
            class="mb-4 flex flex-wrap items-center gap-3 text-sm text-danger"
            role="alert"
        >
            <span>{{ $t('inventory.history.error') }}</span>
            <Button
                type="button"
                variant="outline-secondary"
                @click="table?.reload()"
                >{{ $t('inventory.history.retry') }}</Button
            >
        </div>
        <DataTable
            ref="table"
            :ajax="ajax"
            :columns="columns"
            :options="options"
        >
            <template #whenCell="{ rowData }">{{
                formatWhen(rowData.when)
            }}</template>
            <template #typeCell="{ rowData }">{{
                $t(`inventory.movements.${rowData.type}`)
            }}</template>
            <template #itemCell="{ rowData }">
                <EntitlementItemName
                    :name="rowData.item"
                    :labels="rowData.labels"
                />
            </template>
            <template #giverCell="{ rowData }">{{
                rowData.giver ?? '—'
            }}</template>
            <template #receiverCell="{ rowData }">
                {{ rowData.receiver ?? '—' }}
                <span
                    v-if="rowData.code"
                    class="ml-1 font-mono text-xs text-muted"
                    >{{ rowData.code }}</span
                >
            </template>
            <template #actorCell="{ rowData }">{{
                rowData.recorded_by ?? '—'
            }}</template>
        </DataTable>
    </div>
</template>
