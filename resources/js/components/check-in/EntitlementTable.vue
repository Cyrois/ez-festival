<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { Badge } from '../ui/badge';
import { IconButton } from '../ui/icon-button';
import { Tooltip } from '../ui/tooltip';
import { DataTable } from '../ui/data-table';
import { useFlashToast } from '../../composables/useFlashToast';

const props = defineProps({
    type: { type: String, required: true },
    engagementId: { type: Number, required: true },
    personId: { type: Number, required: true },
    refreshKey: { type: Number, default: 0 },
    timezone: { type: String, required: true },
    canWrite: { type: Boolean, default: true },
    writeBlockedReason: { type: String, default: '' },
});
defineEmits(['consume']);
const table = ref(null);
const { showError } = useFlashToast();
let controller;
let disposed = false;
const load = async (data, callback) => {
    controller?.abort();
    const request = new AbortController();
    controller = request;
    const params = new URLSearchParams({
        type: props.type,
        engagement_id: props.engagementId,
        person_id: props.personId,
        draw: data.draw,
        start: data.start,
        length: data.length,
        'search[value]': data.search?.value ?? '',
        'order[0][column]': data.order?.[0]?.column ?? 0,
        'order[0][dir]': data.order?.[0]?.dir ?? 'asc',
    });
    try {
        const response = await fetch(`/check-in/entitlements?${params}`, {
            headers: { Accept: 'application/json' },
            signal: request.signal,
        });
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (!disposed && !request.signal.aborted) callback(result);
    } catch {
        if (!disposed && !request.signal.aborted) {
            callback({
                draw: data.draw,
                recordsTotal: 0,
                recordsFiltered: 0,
                data: [],
            });
            showError();
        }
    }
};
onUnmounted(() => {
    disposed = true;
    controller?.abort();
});
watch(
    () => props.refreshKey,
    () => table.value?.reload(false),
);
const columns = computed(() => [
    {
        data: 'name',
        title: trans('check_in.entitled_to'),
        render: { display: '#nameCell' },
    },
    {
        data: 'status',
        title: trans('artists.check_in.status_label'),
        render: { display: '#statusCell' },
    },
    {
        data: 'issued.code',
        defaultContent: '',
        title: trans('artists.check_in.code'),
        render: { display: '#codeCell' },
    },
    {
        data: 'issued.issued_by',
        defaultContent: '',
        title: trans('check_in.checked_in_by'),
        render: { display: '#issuerCell' },
    },
    {
        data: 'issued.issued_at',
        defaultContent: '',
        title: trans('check_in.checked_in_at'),
        render: { display: '#timeCell' },
    },
    {
        data: null,
        title: trans('credentials.entitlements.items.table.actions'),
        orderable: false,
        searchable: false,
        render: { display: '#actionsCell' },
    },
]);
const options = computed(() => ({
    serverSide: true,
    paging: false,
    searching: false,
    lengthChange: false,
    order: [[0, 'asc']],
    columnDefs: [
        { targets: [0, 1, 2, 3, 4], className: 'dt-left' },
        { targets: 5, className: 'dt-right' },
    ],
}));
const formatWhen = (value) => {
    if (!value) return trans('artists.not_set');
    return new Intl.DateTimeFormat(getActiveLanguage(), {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: props.timezone,
    }).format(new Date(value));
};
</script>

<template>
    <div class="overflow-x-auto">
        <DataTable
            ref="table"
            :ajax="load"
            :columns="columns"
            :options="options"
        >
            <template #nameCell="{ cellData }">
                <strong>{{ cellData }}</strong>
            </template>
            <template #statusCell="{ cellData }">
                <Badge
                    :variant="cellData === 'issued' ? 'success' : 'warning'"
                    pill
                    >{{
                        $t(`artists.check_in.entitlement_status.${cellData}`)
                    }}</Badge
                >
            </template>
            <template #codeCell="{ cellData }">
                <span class="font-mono text-xs">{{
                    cellData || $t('artists.not_set')
                }}</span>
            </template>
            <template #issuerCell="{ cellData }">
                <span :class="!cellData && 'font-mono text-xs'">{{
                    cellData || $t('artists.not_set')
                }}</span>
            </template>
            <template #timeCell="{ cellData }">
                <span :class="!cellData && 'font-mono text-xs'">{{
                    formatWhen(cellData)
                }}</span>
            </template>
            <template #actionsCell="{ rowData }">
                <div class="flex items-center justify-end gap-2">
                    <template v-if="rowData.status === 'pending'">
                        <Tooltip
                            v-if="!canWrite || rowData.locations.length === 0"
                            :label="
                                !canWrite
                                    ? writeBlockedReason
                                    : $t('check_in.no_stock')
                            "
                        >
                            <IconButton
                                icon="check"
                                tone="primary"
                                :label="$t('artists.check_in.consume')"
                                disabled
                            />
                            <template #content>{{
                                !canWrite
                                    ? writeBlockedReason
                                    : $t('check_in.no_stock')
                            }}</template>
                        </Tooltip>
                        <IconButton
                            v-else
                            icon="check"
                            tone="primary"
                            :label="$t('artists.check_in.consume')"
                            @click="$emit('consume', rowData)"
                        />
                    </template>
                    <IconButton
                        v-else
                        icon="trash-can"
                        :label="$t('artists.check_in.remove')"
                        tone="delete"
                        disabled
                    />
                </div>
            </template>
        </DataTable>
    </div>
</template>
