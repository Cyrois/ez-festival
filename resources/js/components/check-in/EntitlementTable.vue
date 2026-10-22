<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { Badge } from '../ui/badge';
import { Dialog } from '../ui/dialog';
import { EntitlementItemName } from '../ui/entitlement-labels';
import { IconButton } from '../ui/icon-button';
import { Tooltip } from '../ui/tooltip';
import { DataTable } from '../ui/data-table';
import { Switch } from '../ui/switch';
import { useFlashToast } from '../../composables/useFlashToast';

// DataTables caches slot output. Render actions in their own component so
// switching views updates the controls without reloading the table.
const EntitlementActions = (_, { slots }) => slots.default?.();

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
const auditViewCookie = 'check_in_audit_view';
const auditView = ref(
    typeof document !== 'undefined' &&
        document.cookie
            .split(';')
            .some((cookie) => cookie.trim() === `${auditViewCookie}=1`),
);
const details = ref(null);
watch(auditView, (visible) => {
    table.value?.setColumnsVisible([2, 3, 4, 5, 6], visible);
    if (typeof document !== 'undefined') {
        const secure =
            document.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${auditViewCookie}=${visible ? '1' : '0'}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
    }
});
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
        width: '8rem',
        title: trans('artists.check_in.status_label'),
        render: { display: '#statusCell' },
    },
    {
        data: 'issued.code',
        width: '8rem',
        defaultContent: '',
        title: trans('artists.check_in.code'),
        visible: auditView.value,
        render: { display: '#codeCell' },
    },
    {
        data: 'issued.giver',
        width: '12rem',
        defaultContent: '',
        title: trans('inventory.columns.giver'),
        visible: auditView.value,
        render: { display: '#participantCell' },
    },
    {
        data: 'issued.receiver',
        width: '12rem',
        defaultContent: '',
        title: trans('inventory.columns.receiver'),
        visible: auditView.value,
        render: { display: '#participantCell' },
    },
    {
        data: 'issued.issued_at',
        width: '14rem',
        defaultContent: '',
        title: trans('check_in.checked_in_at'),
        visible: auditView.value,
        render: { display: '#timeCell' },
    },
    {
        data: null,
        width: '6rem',
        title: trans('credentials.entitlements.items.table.actions'),
        orderable: false,
        searchable: false,
        render: { display: '#actionsCell' },
    },
]);
const options = computed(() => ({
    serverSide: true,
    info: false,
    paging: false,
    searching: false,
    lengthChange: false,
    order: [[0, 'asc']],
    columnDefs: [
        { targets: [0, 1, 2, 3, 4, 5, 6], className: 'dt-left' },
        { targets: 7, className: 'dt-right' },
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
    <div
        class="min-w-0 overflow-x-auto"
        :class="auditView && '[&_table]:md:min-w-[86rem]'"
    >
        <DataTable
            ref="table"
            class="md:table-fixed"
            :ajax="load"
            :columns="columns"
            :options="options"
        >
            <template #nameCell="{ rowData }">
                <EntitlementItemName
                    :name="rowData.name"
                    :labels="rowData.labels ?? []"
                />
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
                <span class="font-mono text-xs [overflow-wrap:anywhere]">{{
                    cellData || $t('artists.not_set')
                }}</span>
            </template>
            <template #participantCell="{ cellData }">
                <span
                    class="[overflow-wrap:anywhere]"
                    :class="!cellData && 'font-mono text-xs'"
                    >{{ cellData || $t('check_in.absent_endpoint') }}</span
                >
            </template>
            <template #timeCell="{ cellData }">
                <span :class="!cellData && 'font-mono text-xs'">{{
                    formatWhen(cellData)
                }}</span>
            </template>
            <template #actionsCell="{ rowData }">
                <EntitlementActions>
                    <div class="flex items-center justify-end gap-2">
                        <template v-if="rowData.status === 'pending'">
                            <Tooltip
                                v-if="!canWrite || !rowData.can_consume"
                                :label="
                                    rowData.consume_blocked_reason ||
                                    writeBlockedReason
                                "
                            >
                                <IconButton
                                    icon="check"
                                    tone="primary"
                                    :label="$t('artists.check_in.consume')"
                                    disabled
                                />
                                <template #content>{{
                                    rowData.consume_blocked_reason ||
                                    writeBlockedReason
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
                            v-else-if="!auditView"
                            icon="eye"
                            tone="primary"
                            :label="$t('check_in.view_details')"
                            @click="details = rowData"
                        />
                        <IconButton
                            v-else
                            icon="trash-can"
                            :label="$t('artists.check_in.remove')"
                            tone="delete"
                            disabled
                        />
                    </div>
                </EntitlementActions>
            </template>
        </DataTable>
    </div>
    <div class="mt-4 flex justify-end">
        <Switch
            v-model="auditView"
            :label="$t('check_in.audit_view')"
        />
    </div>
    <Dialog
        :open="Boolean(details)"
        :title="$t('check_in.entitlement_details')"
        :description="details?.name"
        :show-confirm="false"
        :cancel-label="$t('ui.dialog.close')"
        role="dialog"
        focus-trap
        @update:open="details = $event ? details : null"
    >
        <template #description>
            <EntitlementItemName
                v-if="details"
                :name="details.name"
                :labels="details.labels ?? []"
                stacked
            />
        </template>
        <dl class="mt-4 mb-0 space-y-4 text-sm">
            <div>
                <dt class="text-xs text-muted">
                    {{ $t('artists.check_in.code') }}
                </dt>
                <dd class="m-0 font-mono break-words">
                    {{ details?.issued?.code || $t('artists.not_set') }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted">
                    {{ $t('check_in.checked_in_by') }}
                </dt>
                <dd class="m-0 break-words">
                    {{ details?.issued?.issued_by || $t('artists.not_set') }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted">
                    {{ $t('check_in.checked_in_at') }}
                </dt>
                <dd class="m-0">
                    {{ formatWhen(details?.issued?.issued_at) }}
                </dd>
            </div>
        </dl>
    </Dialog>
</template>
