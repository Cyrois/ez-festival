<script setup>
import { Button } from '../../components/ui/button';
import { DataTable } from '../../components/ui/data-table';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { SegmentedControl } from '../../components/ui/segmented-control';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import LocationRosterSchedule from '../../components/team/LocationRosterSchedule.vue';
import LocationScheduleGrid from '../../components/team/LocationScheduleGrid.vue';
import { Tab, TabList, TabPanel, Tabs } from '../../components/ui/tabs';
import AppLayout from '../../layouts/AppLayout.vue';
import { navigateDataTableRow } from '../../lib/dataTableRowNavigation';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    scheduleCreateHref,
    scheduleDay,
    scheduleDateLabel,
    scheduleShiftHref,
    scheduleLocationFromUrl,
    schedulePageHref,
    scheduleViewFromUrl,
} from '../../lib/scheduleTimeline';
import { trans } from 'laravel-vue-i18n';
import { shiftColumns } from './shiftColumns';

const props = defineProps({
    event: { type: Object, required: true },
    locations: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
    scheduleDate: { type: String, required: true },
});

const page = usePage();
const initialTab = new URLSearchParams(page.url.split('?')[1] ?? '').get('tab');
const activeTab = ref(
    ['schedule', 'list'].includes(initialTab) ? initialTab : 'schedule',
);
const selectedDate = ref(props.scheduleDate);
const scheduleView = ref(scheduleViewFromUrl(page.url, props.locations));
const selectedLocation = ref(
    scheduleLocationFromUrl(page.url, props.locations) ||
        (scheduleView.value === 'location_shifts'
            ? (props.locations[0]?.id ?? '')
            : ''),
);
const viewOptions = computed(() => [
    {
        value: 'all_locations',
        label: trans('team.scheduling.views.all_locations'),
        icon: ['fas', 'table-columns'],
    },
    {
        value: 'location_shifts',
        label: trans('team.scheduling.views.location_shifts'),
        icon: ['fas', 'list'],
    },
]);
const selectView = (view) => {
    scheduleView.value = view;
    if (view === 'location_shifts' && !selectedLocation.value)
        selectedLocation.value = props.locations[0]?.id ?? '';
};
const locationItems = computed(() => [
    ...(scheduleView.value === 'all_locations'
        ? [{ value: '', title: trans('team.scheduling.grid.all_locations') }]
        : []),
    ...props.locations.map((location) => ({
        value: location.id,
        title: location.name,
    })),
]);
const locale = computed(() => page.props.locale ?? undefined);
const dateLabel = computed(() =>
    scheduleDateLabel(selectedDate.value, locale.value, { year: 'numeric' }),
);
const selectDate = (date) => {
    if (/^\d{4}-\d{2}-\d{2}$/.test(date)) selectedDate.value = date;
};
watch(
    () => props.scheduleDate,
    (date) => {
        selectedDate.value = date;
    },
);
watch(
    () => page.url,
    (url) => {
        scheduleView.value = scheduleViewFromUrl(url, props.locations);
        selectedLocation.value = scheduleLocationFromUrl(url, props.locations);
        if (scheduleView.value === 'location_shifts' && !selectedLocation.value)
            selectedLocation.value = props.locations[0]?.id ?? '';
        const tab = new URLSearchParams(url.split('?')[1] ?? '').get('tab');
        activeTab.value = ['schedule', 'list'].includes(tab) ? tab : 'schedule';
    },
);
watch(
    [selectedDate, activeTab, selectedLocation, scheduleView],
    ([date, tab, location, view]) => {
        const url = schedulePageHref(date, tab, location, view);
        if (page.url === url) return;
        router.replace({
            url,
            props: (current) => ({ ...current, scheduleDate: date }),
            preserveState: true,
            preserveScroll: true,
        });
    },
);
const createShift = (selection) => {
    if (!canWrite.value) return;
    router.get(
        scheduleCreateHref(
            selectedDate.value,
            'schedule',
            selection,
            selectedLocation.value,
            scheduleView.value,
        ),
    );
};
const canWrite = computed(() => props.canManage && !props.event.is_locked);
const columns = computed(() => shiftColumns(trans));
const tableOptions = computed(() => ({
    lengthChange: false,
    order: [[2, 'asc']],
    pageLength: 25,
    serverSide: true,
    columnDefs: [
        { targets: [2, 3], className: 'text-left' },
        { targets: 7, className: 'text-right' },
    ],
    createdRow: (row, shift) =>
        navigateDataTableRow(row, shift, (item) =>
            scheduleShiftHref(
                item.id,
                selectedDate.value,
                'list',
                selectedLocation.value,
                scheduleView.value,
            ),
        ),
    language: {
        emptyTable: trans('team.scheduling.empty_list'),
        searchPlaceholder: trans('team.scheduling.search'),
        zeroRecords: trans('team.scheduling.no_matches'),
    },
}));
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.scheduling') },
]);

const shiftName = (shift) =>
    shift.name || trans('team.scheduling.unnamed_shift');

const formatDateTime = (value) =>
    new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
</script>

<template>
    <AppLayout
        :title="$t('team.scheduling.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <header
                class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ $t('team.scheduling.heading') }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{
                            $t(
                                activeTab === 'schedule'
                                    ? scheduleView === 'location_shifts'
                                        ? 'team.scheduling.roster.lead'
                                        : 'team.scheduling.grid.lead'
                                    : 'team.scheduling.lead',
                            )
                        }}
                    </p>
                </div>
                <Button
                    :title="
                        !canWrite
                            ? $t(
                                  event.is_locked
                                      ? 'team.scheduling.locked'
                                      : 'permissions.no_add.shifts',
                              )
                            : undefined
                    "
                    class="w-full sm:w-auto"
                    :disabled="!canWrite || locations.length === 0"
                    :href="
                        scheduleCreateHref(
                            selectedDate,
                            activeTab,
                            null,
                            selectedLocation,
                            scheduleView,
                        )
                    "
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                        class="mr-1.5"
                    />
                    {{ $t('team.scheduling.actions.new') }}
                </Button>
            </header>

            <p
                v-if="event.is_locked"
                class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                <Icon :name="['fas', 'lock']" />
                {{ $t('team.scheduling.locked') }}
            </p>

            <p
                v-else-if="locations.length === 0"
                class="mb-4 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                {{ $t('team.scheduling.no_locations') }}
            </p>

            <Tabs v-model="activeTab">
                <TabList class="overflow-visible">
                    <Tab value="schedule">
                        {{ $t('team.scheduling.tabs.schedule') }}
                    </Tab>
                    <Tab value="list">
                        {{ $t('team.scheduling.tabs.list') }}
                    </Tab>
                </TabList>

                <TabPanel value="schedule">
                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <Button
                            variant="outline-primary"
                            size="icon"
                            :aria-label="
                                $t('team.scheduling.grid.previous_day')
                            "
                            @click="
                                selectedDate = scheduleDay(selectedDate, -1)
                            "
                        >
                            <Icon :name="['fas', 'chevron-left']" />
                        </Button>
                        <span class="text-sm font-semibold">{{
                            dateLabel
                        }}</span>
                        <Button
                            variant="outline-primary"
                            size="icon"
                            :aria-label="$t('team.scheduling.grid.next_day')"
                            @click="selectedDate = scheduleDay(selectedDate, 1)"
                        >
                            <Icon :name="['fas', 'chevron-right']" />
                        </Button>
                        <div class="w-40">
                            <Input
                                :model-value="selectedDate"
                                type="date"
                                :aria-label="
                                    $t('team.scheduling.grid.select_day')
                                "
                                @update:model-value="selectDate"
                            />
                        </div>
                        <div class="w-52">
                            <CustomDropdown
                                v-model="selectedLocation"
                                :items="locationItems"
                                :placeholder="
                                    $t('team.scheduling.grid.all_locations')
                                "
                                :aria-label="
                                    $t('team.scheduling.fields.location')
                                "
                            />
                        </div>
                        <SegmentedControl
                            :model-value="scheduleView"
                            :options="viewOptions"
                            variant="joined"
                            class="ml-auto shrink-0"
                            :aria-label="$t('team.scheduling.views.label')"
                            @update:model-value="selectView"
                        />
                    </div>
                    <LocationRosterSchedule
                        v-if="
                            activeTab === 'schedule' &&
                            scheduleView === 'location_shifts' &&
                            selectedLocation
                        "
                        :date="selectedDate"
                        :location-id="selectedLocation"
                        :location-name="
                            locations.find(
                                (location) => location.id === selectedLocation,
                            )?.name ?? ''
                        "
                        :event-id="event.id"
                        :can-assign="canWrite"
                        :disabled-reason="
                            !canWrite
                                ? $t(
                                      event.is_locked
                                          ? 'team.scheduling.locked'
                                          : 'team.scheduling.no_permission',
                                  )
                                : ''
                        "
                    />
                    <LocationScheduleGrid
                        v-else-if="activeTab === 'schedule'"
                        :date="selectedDate"
                        :location-id="selectedLocation"
                        :event-id="event.id"
                        :can-create="canWrite && locations.length > 0"
                        @create="createShift"
                    />
                </TabPanel>

                <TabPanel value="list">
                    <DataTable
                        v-if="activeTab === 'list'"
                        ajax="/team/scheduling/shifts"
                        :columns="columns"
                        :options="tableOptions"
                    >
                        <template #nameCell="{ rowData }">
                            <Link
                                :href="
                                    scheduleShiftHref(
                                        rowData.id,
                                        selectedDate,
                                        'list',
                                        selectedLocation,
                                        scheduleView,
                                    )
                                "
                                class="cursor-pointer font-semibold text-charcoal no-underline hover:text-primary hover:underline"
                            >
                                {{ shiftName(rowData) }}
                            </Link>
                        </template>
                        <template #startCell="{ cellData }">
                            {{ formatDateTime(cellData) }}
                        </template>
                        <template #endCell="{ cellData }">
                            {{ formatDateTime(cellData) }}
                        </template>
                        <template #rolesCell="{ rowData }">
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="slot in rowData.slots"
                                    :key="slot.id"
                                    class="rounded-lg border border-line bg-page px-2 py-0.5 text-xs text-charcoal"
                                    >{{
                                        $t('team.scheduling.slots.role_count', {
                                            role: slot.role_name,
                                            count: slot.needed,
                                        })
                                    }}</span
                                >
                                <span v-if="!rowData.slots.length">{{
                                    $t('data_table.empty_value')
                                }}</span>
                            </div>
                        </template>
                        <template #mealsCell="{ rowData }">{{
                            (rowData.meals ?? [])
                                .map((row) => row.meal.name)
                                .join(', ') || $t('data_table.empty_value')
                        }}</template>
                        <template #needsCell="{ rowData }">
                            {{
                                $t('team.scheduling.slots.filled', {
                                    filled: rowData.filled_count,
                                    count: rowData.total_needs,
                                })
                            }}
                            <p
                                v-if="rowData.extra_count"
                                class="m-0 mt-1 text-xs text-warning"
                            >
                                {{
                                    $t(
                                        rowData.extra_count === 1
                                            ? 'team.scheduling.assignments.extra_one'
                                            : 'team.scheduling.assignments.extra_many',
                                        { count: rowData.extra_count },
                                    )
                                }}
                            </p>
                        </template>
                        <template #openCell="{ rowData }">
                            <Link
                                :href="
                                    scheduleShiftHref(
                                        rowData.id,
                                        selectedDate,
                                        'list',
                                        selectedLocation,
                                        scheduleView,
                                    )
                                "
                                class="inline-flex text-muted hover:text-primary"
                                :aria-label="
                                    $t('data_table.open', {
                                        name: shiftName(rowData),
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
                </TabPanel>
            </Tabs>
        </div>
    </AppLayout>
</template>
