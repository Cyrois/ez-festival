<script setup>
import { computed, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { Card, CardTitle } from '../ui/card';
import { DataTable } from '../ui/data-table';
import { useFlashToast } from '../../composables/useFlashToast';
import { scheduleDateLabel } from '../../lib/scheduleTimeline';
import { memberShiftTimeLabel } from '../../lib/memberShifts';

const props = defineProps({ memberId: { type: Number, required: true } });
const { showError } = useFlashToast();
const locale = computed(() => getActiveLanguage());
let controller;
let disposed = false;

const loadShifts = async (data, callback) => {
    controller?.abort();
    const request = new AbortController();
    controller = request;
    const params = new URLSearchParams({
        draw: data.draw,
        start: data.start,
        length: data.length,
        'search[value]': data.search?.value ?? '',
        'order[0][column]': data.order?.[0]?.column ?? 0,
        'order[0][dir]': data.order?.[0]?.dir ?? 'asc',
    });
    try {
        const response = await fetch(
            `/team/members/${props.memberId}/shifts?${params}`,
            {
                headers: { Accept: 'application/json' },
                signal: request.signal,
            },
        );
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (!disposed && !request.signal.aborted) callback(result);
    } catch (error) {
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

const columns = computed(() => [
    {
        data: 'day',
        title: trans('team.member.shifts.day'),
        render: { display: '#dayCell' },
    },
    {
        data: 'location',
        title: trans('team.scheduling.table.location'),
        render: { display: '#locationCell' },
    },
    {
        data: 'starts_at',
        title: trans('team.member.shifts.time'),
        render: { display: '#timeCell' },
    },
    {
        data: 'role_name',
        title: trans('team.scheduling.slots.role'),
        render: { display: '#roleCell' },
    },
]);
const options = computed(() => ({
    serverSide: true,
    pageLength: 25,
    lengthChange: false,
    layout: {
        bottomEnd: { paging: { firstLast: false } },
    },
    order: [[0, 'asc']],
    columnDefs: [{ targets: '_all', className: 'dt-left' }],
    language: { emptyTable: trans('team.member.shifts.empty') },
}));
</script>

<template>
    <Card
        id="shifts"
        class="mt-4"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <CardTitle>
                {{ $t('team.member.sections.shifts.title') }}
            </CardTitle>
            <Link
                href="/team/scheduling?tab=schedule"
                class="text-sm font-semibold text-secondary no-underline hover:underline"
            >
                {{ $t('team.member.shifts.open_scheduling') }}
            </Link>
        </div>
        <p class="mt-1 mb-4 text-xs text-muted">
            {{ $t('team.member.sections.shifts.description') }}
        </p>
        <div class="overflow-x-auto">
            <DataTable
                :ajax="loadShifts"
                :columns="columns"
                :options="options"
            >
                <template #dayCell="{ cellData }">
                    {{ scheduleDateLabel(cellData, locale) }}
                </template>
                <template #locationCell="{ cellData }">
                    {{ cellData }}
                </template>
                <template #timeCell="{ rowData }">
                    {{ memberShiftTimeLabel(rowData, locale, trans) }}
                </template>
                <template #roleCell="{ cellData }">
                    {{ cellData ?? $t('team.scheduling.assignments.no_role') }}
                </template>
            </DataTable>
        </div>
    </Card>
</template>
