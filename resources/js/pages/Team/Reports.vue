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
    groupOptions: { type: Array, default: () => [] },
});
const page = usePage();
const eventId = computed(() => page.props.activeEvent.id);
const startDate = ref(props.filters.start_date ?? '');
const endDate = ref(props.filters.end_date ?? '');
const selectedGroup = ref(props.filters.group_id ?? '');
const groups = shallowRef(props.groupOptions);
const activeView = ref(props.shiftFilters.view ?? 'group');
const selectedDate = ref(props.shiftFilters.date ?? '');
const visitedShift = ref(
    activeView.value === 'shift' ||
        Boolean(new URLSearchParams(page.url.split('?')[1] ?? '').get('date')),
);
const groupActive = computed(() => activeView.value === 'group');
const shiftReport = reactive(
    useShiftHoursReportRequests({
        eventId,
        selectedDate,
        active: computed(() => activeView.value === 'shift'),
    }),
);
watch(
    () => props.shiftFilters,
    (filters) => {
        activeView.value = filters.view ?? 'group';
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
    selectedGroup,
    startDate,
    endDate,
    groups,
    active: groupActive,
});
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
        startDate.value = filters.start_date ?? '';
        endDate.value = filters.end_date ?? '';
    },
);

const syncAddress = () => {
    if (activeView.value === 'group' && Object.keys(rangeErrors.value).length)
        return;
    if (activeView.value === 'shift') visitedShift.value = true;
    const params = new URLSearchParams();
    if (activeView.value === 'shift') params.set('view', 'shift');
    if (visitedShift.value && selectedDate.value)
        params.set('date', selectedDate.value);
    if (selectedGroup.value !== '') params.set('group_id', selectedGroup.value);
    if (startDate.value && !Object.keys(rangeErrors.value).length) {
        params.set('start_date', startDate.value);
        params.set('end_date', endDate.value);
    }
    const url = '/team/reports' + (params.size ? '?' + params : '');
    if (page.url === url) return;
    const filters = {
        group_id: selectedGroup.value || null,
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
    [eventId, selectedGroup, startDate, endDate, activeView, selectedDate],
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
    <AppLayout
        :title="$t('team.reports.title')"
        :breadcrumbs="breadcrumbs"
    >
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
            <Tabs v-model="activeView">
                <TabList>
                    <Tab value="group">{{ $t('team.reports.by_group') }}</Tab>
                    <Tab value="shift">{{ $t('team.reports.by_shift') }}</Tab>
                </TabList>
                <TabPanel
                    value="group"
                    class="space-y-6"
                >
                    <HoursReportFilters
                        v-if="groupActive"
                        v-model:group-id="selectedGroup"
                        v-model:start-date="startDate"
                        v-model:end-date="endDate"
                        :groups="groups"
                        :errors="errors"
                    />
                    <section
                        v-if="groupActive"
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
                                :request-key="key"
                                :showing-people="showingPeople"
                                :can-view-team="canViewTeam"
                                @select-group="selectedGroup = $event"
                            />
                        </template>
                    </section>
                </TabPanel>
                <TabPanel value="shift">
                    <ShiftHoursReport
                        v-if="!groupActive"
                        v-model:date="selectedDate"
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
            </Tabs>
        </div>
    </AppLayout>
</template>
