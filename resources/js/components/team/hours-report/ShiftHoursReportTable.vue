<script setup>
import { computed, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { DataTable } from '../../ui/data-table';
import { Button } from '../../ui/button';
import { Badge } from '../../ui/badge';
import { Icon } from '../../ui/icon';
import HoursReportTable from './HoursReportTable.vue';
import { activateDataTableRow } from '../../../lib/dataTableRowNavigation';
import { formatHoursReportDuration } from '../../../lib/hoursReport';

const props = defineProps({
    rows: { type: Array, required: true },
    openedShift: { type: Number, default: null },
    people: { type: Object, default: null },
    canViewTeam: { type: Boolean, default: false },
});
const emit = defineEmits(['toggle-shift', 'load-people']);
// DataTables caches cell slots until its row data changes.
const tableRows = computed(() =>
    props.rows.map((row) => ({
        ...row,
        is_open: row.shift_id === props.openedShift,
    })),
);
const shiftName = (shift) =>
    shift.name || trans('team.scheduling.unnamed_shift');
const columns = computed(() => [
    {
        data: 'name',
        title: trans('team.scheduling.table.shift'),
        render: { display: '#nameCell' },
    },
    {
        data: 'location',
        title: trans('team.scheduling.table.location'),
        render: { display: '#locationCell' },
    },
    {
        data: 'starts_at',
        title: trans('team.reports.time'),
        render: { display: '#timeCell' },
    },
    { data: 'people_count', title: trans('team.reports.people') },
    {
        data: 'supervisor_name',
        title: trans('team.scheduling.supervisor.tag'),
        render: { display: '#supervisorCell' },
    },
    ...['scheduled', 'approved', 'unapproved'].map((type) => ({
        data: `${type}_minutes`,
        title: trans(`team.reports.${type}`),
        render: { display: `#${type}Cell` },
    })),
    { data: null, title: '', render: { display: '#actionCell' } },
]);
let focusedShift = null;
watch(
    [() => props.rows, () => props.openedShift],
    () => {
        const button = document.activeElement;
        if (button?.matches('[data-shift-toggle]'))
            focusedShift = Number(button.dataset.shiftToggle);
    },
    { flush: 'sync' },
);
const tableOptions = computed(() => ({
    paging: false,
    searching: false,
    ordering: false,
    info: false,
    layout: {
        topStart: null,
        topEnd: null,
        bottomStart: null,
        bottomEnd: null,
    },
    columnDefs: [
        { targets: [3, 5, 6, 7], className: 'text-right tabular-nums' },
        { targets: 4, className: 'dt-record-wide' },
        {
            targets: 8,
            className: 'dt-row-action dt-expansion-action w-12 text-right',
        },
    ],
    drawCallback: function () {
        if (focusedShift === null) return;
        this.api()
            .row((_, row) => row.shift_id === focusedShift)
            .node()
            ?.querySelector('[data-shift-toggle]')
            ?.focus();
        focusedShift = null;
    },
    createdRow: (row, shift) => {
        row.classList.add('dt-expandable');
        activateDataTableRow(row, shift, (item) =>
            emit('toggle-shift', item.shift_id),
        );
    },
}));
</script>

<template>
    <DataTable
        :data="tableRows"
        :columns="columns"
        :options="tableOptions"
        row-key="shift_id"
        :expanded-row-key="openedShift"
        mobile-layout="summary"
    >
        <template #nameCell="{ rowData }"
            ><span class="font-semibold">{{
                shiftName(rowData)
            }}</span></template
        >
        <template #timeCell="{ rowData }">
            <span class="whitespace-nowrap"
                >{{ rowData.starts_at.slice(11, 16) }}–{{
                    rowData.ends_at.slice(11, 16)
                }}</span
            >
            <Badge
                v-if="
                    rowData.starts_at.slice(0, 10) !==
                    rowData.ends_at.slice(0, 10)
                "
                pill
                class="mt-1 block w-fit text-muted"
                >{{ $t('team.reports.overnight') }}</Badge
            >
        </template>
        <template #locationCell="{ rowData }">{{ rowData.location }}</template>
        <template #supervisorCell="{ rowData }">{{
            rowData.supervisor_name || $t('data_table.empty_value')
        }}</template>
        <template #scheduledCell="{ rowData }">{{
            formatHoursReportDuration(rowData.scheduled_minutes)
        }}</template>
        <template #approvedCell
            ><span class="text-muted">{{
                $t('team.reports.unavailable')
            }}</span></template
        >
        <template #unapprovedCell
            ><span class="text-muted">{{
                $t('team.reports.unavailable')
            }}</span></template
        >
        <template #actionCell="{ rowData }">
            <Button
                variant="ghost"
                size="icon"
                :data-shift-toggle="rowData.shift_id"
                :aria-expanded="openedShift === rowData.shift_id"
                :aria-controls="
                    openedShift === rowData.shift_id
                        ? `hours-shift-${rowData.shift_id}-people`
                        : undefined
                "
                :aria-label="
                    $t(
                        openedShift === rowData.shift_id
                            ? 'team.reports.close_shift'
                            : 'team.reports.open_shift',
                        { name: shiftName(rowData) },
                    )
                "
                @click="emit('toggle-shift', rowData.shift_id)"
            >
                <Icon
                    :name="[
                        'fas',
                        openedShift === rowData.shift_id
                            ? 'chevron-down'
                            : 'chevron-right',
                    ]"
                />
            </Button>
        </template>
        <template #expandedRow="{ rowData }">
            <div
                :id="`hours-shift-${rowData.shift_id}-people`"
                :aria-label="
                    $t('team.reports.shift_people', {
                        name: shiftName(rowData),
                    })
                "
                :aria-busy="people?.status === 'loading'"
            >
                <p
                    v-if="people?.status === 'loading'"
                    role="status"
                    class="p-4 text-sm text-muted"
                >
                    {{ $t('team.reports.people_loading') }}
                </p>
                <p
                    v-else-if="people?.status === 'failed'"
                    role="alert"
                    class="p-4 text-sm text-danger"
                >
                    {{
                        $t('team.reports.people_load_failed', {
                            name: shiftName(rowData),
                        })
                    }}
                    <Button
                        variant="link"
                        @click="emit('load-people')"
                        >{{ $t('team.reports.retry') }}</Button
                    >
                </p>
                <HoursReportTable
                    v-else-if="people?.status === 'loaded'"
                    :rows="people.data.people"
                    :request-key="String(openedShift)"
                    showing-people
                    shift-people
                    :can-view-team="canViewTeam"
                />
            </div>
        </template>
    </DataTable>
</template>
