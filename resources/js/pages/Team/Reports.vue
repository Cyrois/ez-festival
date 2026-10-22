<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, shallowRef, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Tab, TabList, TabPanel, Tabs } from '../../components/ui/tabs';
import ShiftHoursReport from '../../components/team/hours-report/ShiftHoursReport.vue';
import { useShiftHoursReportRequests } from '../../composables/useShiftHoursReportRequests';
import { EmptyState } from '../../components/ui/empty-state';
import HoursReportFilters from '../../components/team/hours-report/HoursReportFilters.vue';
import HoursReportSummary from '../../components/team/hours-report/HoursReportSummary.vue';
import HoursReportLoadState from '../../components/team/hours-report/HoursReportLoadState.vue';
import HoursReportTable from '../../components/team/hours-report/HoursReportTable.vue';
import { useHoursReportRequests } from '../../composables/useHoursReportRequests';

const props = defineProps({
    shiftFilters: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    employmentOptions: { type: Array, default: () => [] },
    locationOptions: { type: Array, default: () => [] },
    groupOptions: { type: Array, default: () => [] },
});
const page = usePage();
const eventId = computed(() => page.props.activeEvent.id);
const employmentType = ref(props.filters.employment_type ?? 'all');
const startDate = ref(props.filters.start_date ?? '');
const endDate = ref(props.filters.end_date ?? '');
const selectedGroup = ref(props.filters.group_id ?? '');
const selectedLocation = ref(props.filters.location_id ?? '');
const locations = shallowRef(props.locationOptions);
const groups = shallowRef(props.groupOptions);
const activeView = ref(
    props.shiftFilters.view ?? (props.filters.group_id ? 'team' : 'summary'),
);
const selectedDate = ref(props.shiftFilters.date ?? '');
const visitedShift = ref(
    activeView.value === 'shift' ||
        Boolean(new URLSearchParams(page.url.split('?')[1] ?? '').get('date')),
);
const groupActive = computed(() =>
    ['summary', 'team'].includes(activeView.value),
);
const requestedGroup = computed({
    get: () => (activeView.value === 'team' ? selectedGroup.value : ''),
    set: (value) => {
        selectedGroup.value = value;
    },
});
const rememberKey = computed(
    () =>
        `artist-tree.hours-report.last-team.${page.props.auth?.user?.id}.${eventId.value}`,
);
const restoreTeam = () => {
    if (activeView.value !== 'team' || selectedGroup.value !== '') return;
    let saved = null;
    try {
        saved = localStorage.getItem(rememberKey.value);
    } catch {
        /* Storage may be disabled. */
    }
    selectedGroup.value =
        saved === null
            ? (groups.value[0]?.group_id ?? '')
            : (groups.value.find((group) => String(group.group_id) === saved)
                  ?.group_id ?? '');
};
watch(eventId, () => {
    if (activeView.value !== 'shift') activeView.value = 'summary';
    selectedGroup.value = '';
    selectedLocation.value = '';
    groups.value = [];
    locations.value = [];
});
watch([activeView, rememberKey], restoreTeam, { immediate: true });
watch(selectedGroup, (value) => {
    if (activeView.value !== 'team' || value === '') return;
    try {
        localStorage.setItem(rememberKey.value, String(value));
    } catch {
        /* Storage may be disabled. */
    }
});
const selectGroup = (groupId) => {
    selectedGroup.value = groupId;
    activeView.value = 'team';
    try {
        localStorage.setItem(rememberKey.value, String(groupId));
    } catch {
        /* Storage may be disabled. */
    }
};
const shiftReport = reactive(
    useShiftHoursReportRequests({
        eventId,
        employmentType,
        selectedDate,
        active: computed(() => activeView.value === 'shift'),
    }),
);
watch(
    () => props.shiftFilters,
    (filters) => {
        activeView.value =
            filters.view ?? (props.filters.group_id ? 'team' : 'summary');
        selectedDate.value = filters.date ?? '';
    },
);

const {
    key,
    report,
    loading,
    failed,
    showingPeople,
    summary,
    rangeErrors,
    errors,
    rows,
    load,
} = useHoursReportRequests({
    eventId,
    employmentType,
    selectedGroup: requestedGroup,
    startDate,
    endDate,
    groups,
    active: computed(
        () =>
            groupActive.value &&
            (activeView.value === 'summary' || selectedGroup.value !== ''),
    ),
});
const locationReport = reactive(
    useHoursReportRequests({
        eventId,
        employmentType,
        selectedGroup: selectedLocation,
        startDate,
        endDate,
        groups: locations,
        active: computed(() => activeView.value === 'location'),
        scope: 'location',
    }),
);
const locationName = computed(
    () =>
        locations.value.find(
            (location) => location.location_id === selectedLocation.value,
        )?.name ?? '',
);
const locationHref = (location) =>
    '/team/reports?' + reportParameters('location', location.location_id);
watch(
    () => props.locationOptions,
    (options) => {
        locations.value = options;
    },
);
const canViewTeam = computed(() =>
    Boolean(page.props.permissions?.['team.view']),
);
const groupName = computed(
    () =>
        groups.value.find((group) => group.group_id === selectedGroup.value)
            ?.name ?? '',
);
watch(
    () => props.filters,
    (filters) => {
        selectedGroup.value = filters.group_id ?? '';
        selectedLocation.value = filters.location_id ?? '';
        employmentType.value = filters.employment_type ?? 'all';
        startDate.value = filters.start_date ?? '';
        endDate.value = filters.end_date ?? '';
    },
);

const reportParameters = (
    view = activeView.value,
    locationId = selectedLocation.value,
) => {
    const params = new URLSearchParams();
    if (view !== 'summary' || selectedGroup.value !== '')
        params.set('view', view);
    if (visitedShift.value && selectedDate.value)
        params.set('date', selectedDate.value);
    if (selectedGroup.value !== '') params.set('group_id', selectedGroup.value);
    if (locationId !== '') params.set('location_id', locationId);
    if (startDate.value && !Object.keys(rangeErrors.value).length) {
        params.set('start_date', startDate.value);
        params.set('end_date', endDate.value);
    }
    if (employmentType.value !== 'all')
        params.set('employment_type', employmentType.value);
    return params;
};

const syncAddress = () => {
    if (activeView.value !== 'shift' && Object.keys(rangeErrors.value).length)
        return;
    if (activeView.value === 'shift') visitedShift.value = true;
    const params = reportParameters();
    const url = '/team/reports' + (params.size ? '?' + params : '');
    if (page.url === url) return;
    const filters = {
        employment_type: employmentType.value,
        group_id: selectedGroup.value || null,
        location_id: selectedLocation.value || null,
        start_date: startDate.value || null,
        end_date: endDate.value || null,
    };
    const shiftFilters = { view: activeView.value, date: selectedDate.value };
    const groupOptions = groups.value;
    router.replace({
        url,
        props: (current) => ({
            ...current,
            filters,
            shiftFilters,
            groupOptions,
        }),
        preserveState: true,
        preserveScroll: true,
    });
};

watch(
    [
        eventId,
        selectedGroup,
        selectedLocation,
        startDate,
        endDate,
        activeView,
        selectedDate,
        employmentType,
    ],
    syncAddress,
    {
        immediate: true,
    },
);

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    {
        label: trans('team.title'),
        href: page.props.permissions?.['team.view']
            ? '/team/advancement'
            : undefined,
    },
    { label: trans('nav.team.reports') },
]);
</script>

<template>
    <Tabs v-model="activeView">
        <AppLayout
            :title="$t('team.reports.title')"
            :breadcrumbs="breadcrumbs"
        >
            <template #section-nav>
                <TabList
                    :aria-label="$t('team.reports.title')"
                    class="border-b-0"
                >
                    <Tab
                        value="summary"
                        :aria-current="
                            activeView === 'summary' ? 'page' : undefined
                        "
                        class="mb-0 min-h-11 shrink-0 px-2 whitespace-nowrap sm:px-3"
                    >
                        {{ $t('team.reports.summary') }}
                    </Tab>
                    <Tab
                        value="team"
                        :aria-current="
                            activeView === 'team' ? 'page' : undefined
                        "
                        class="mb-0 min-h-11 shrink-0 px-2 whitespace-nowrap sm:px-3"
                    >
                        {{ $t('team.reports.by_group') }}
                    </Tab>
                    <Tab
                        value="shift"
                        :aria-current="
                            activeView === 'shift' ? 'page' : undefined
                        "
                        class="mb-0 min-h-11 shrink-0 px-2 whitespace-nowrap sm:px-3"
                    >
                        {{ $t('team.reports.by_shift') }}
                    </Tab>
                    <Tab
                        value="location"
                        :aria-current="
                            activeView === 'location' ? 'page' : undefined
                        "
                        class="mb-0 min-h-11 shrink-0 px-2 whitespace-nowrap sm:px-3"
                    >
                        {{ $t('team.reports.by_location') }}
                    </Tab>
                </TabList>
            </template>
            <div class="container mx-auto space-y-6">
                <div>
                    <h1 class="text-2xl font-bold text-charcoal">
                        {{ $t('team.reports.title') }}
                    </h1>
                    <p
                        class="mt-1 text-sm text-muted"
                        :class="groupActive && showingPeople && 'max-md:hidden'"
                    >
                        {{
                            $t('team.reports.description', {
                                event: page.props.activeEvent.name,
                            })
                        }}
                    </p>
                </div>
                <TabPanel
                    v-for="view in ['summary', 'team']"
                    :key="view"
                    :value="view"
                    class="space-y-6"
                >
                    <template v-if="activeView === view">
                        <HoursReportFilters
                            v-if="groupActive"
                            v-model:employment-type="employmentType"
                            v-model:group-id="selectedGroup"
                            v-model:start-date="startDate"
                            v-model:end-date="endDate"
                            :employment-options="employmentOptions"
                            :groups="groups"
                            :showing-people="view === 'team'"
                            :errors="errors"
                        />
                        <EmptyState
                            v-if="view === 'team' && selectedGroup === ''"
                            :title="$t('team.reports.choose_team')"
                            :description="
                                $t('team.reports.choose_team_description')
                            "
                        />
                        <section
                            v-else
                            :aria-label="
                                showingPeople
                                    ? $t('team.reports.group_total', {
                                          name: groupName,
                                      })
                                    : $t('team.reports.event_total')
                            "
                            :aria-busy="loading"
                            class="space-y-5"
                        >
                            <HoursReportSummary
                                :summary="summary"
                                :employment-type="employmentType"
                                :showing-people="showingPeople"
                                :loading="loading"
                            />
                            <HoursReportLoadState
                                v-if="loading || failed"
                                :loading="loading"
                                :showing-people="showingPeople"
                                :group-name="groupName"
                                @retry="load"
                            />
                            <template v-else-if="report">
                                <EmptyState
                                    v-if="rows.length === 0"
                                    :title="
                                        $t(
                                            showingPeople
                                                ? 'team.reports.people_empty'
                                                : 'team.reports.empty',
                                        )
                                    "
                                    :description="
                                        $t(
                                            showingPeople
                                                ? 'team.reports.people_empty_description'
                                                : 'team.reports.empty_description',
                                        )
                                    "
                                />
                                <HoursReportTable
                                    v-else
                                    :rows="rows"
                                    :employment-type="employmentType"
                                    :request-key="key"
                                    :showing-people="showingPeople"
                                    :can-view-team="canViewTeam"
                                    @select-group="selectGroup"
                                />
                            </template>
                        </section>
                    </template>
                </TabPanel>
                <TabPanel value="location">
                    <template v-if="activeView === 'location'">
                        <HoursReportFilters
                            v-model:location-id="selectedLocation"
                            v-model:start-date="startDate"
                            v-model:end-date="endDate"
                            v-model:employment-type="employmentType"
                            :employment-options="employmentOptions"
                            :locations="locations"
                            :errors="locationReport.errors"
                            by-location
                        />
                        <section
                            :aria-label="
                                selectedLocation === ''
                                    ? $t('team.reports.event_total')
                                    : $t('team.reports.location_total', {
                                          name: locationName,
                                      })
                            "
                            :aria-busy="locationReport.loading"
                            class="mt-6 space-y-5"
                        >
                            <HoursReportSummary
                                :summary="locationReport.summary"
                                compact
                                :employment-type="employmentType"
                                :showing-people="locationReport.showingPeople"
                                :loading="locationReport.loading"
                            />
                            <HoursReportLoadState
                                v-if="
                                    locationReport.loading ||
                                    locationReport.failed
                                "
                                :loading="locationReport.loading"
                                :showing-people="locationReport.showingPeople"
                                :group-name="locationName"
                                @retry="locationReport.load"
                            />
                            <template v-else-if="locationReport.report">
                                <EmptyState
                                    v-if="locationReport.rows.length === 0"
                                    :title="
                                        $t(
                                            locationReport.showingPeople
                                                ? 'team.reports.location_people_empty'
                                                : 'team.reports.locations_empty',
                                        )
                                    "
                                    :description="
                                        $t(
                                            'team.reports.locations_empty_description',
                                        )
                                    "
                                />
                                <HoursReportTable
                                    v-else
                                    :rows="locationReport.rows"
                                    :request-key="locationReport.key"
                                    :showing-people="
                                        locationReport.showingPeople
                                    "
                                    :employment-type="employmentType"
                                    :can-view-team="canViewTeam"
                                    :row-href="locationHref"
                                    location-rows
                                />
                            </template>
                        </section>
                    </template>
                </TabPanel>
                <TabPanel value="shift">
                    <ShiftHoursReport
                        v-if="activeView === 'shift'"
                        v-model:date="selectedDate"
                        v-model:employment-type="employmentType"
                        :employment-options="employmentOptions"
                        :day="shiftReport.day"
                        :people="shiftReport.people"
                        :rows="shiftReport.rows"
                        :opened-shift="shiftReport.openedShift"
                        :date-error="shiftReport.dateError"
                        :can-view-team="canViewTeam"
                        :locale="page.props.locale ?? 'en'"
                        @load-day="shiftReport.loadDay"
                        @load-people="shiftReport.loadPeople"
                        @toggle-shift="shiftReport.toggleShift"
                    />
                </TabPanel>
            </div>
        </AppLayout>
    </Tabs>
</template>
