<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, shallowRef, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { EmptyState } from '../../components/ui/empty-state';
import HoursReportFilters from '../../components/team/hours-report/HoursReportFilters.vue';
import HoursReportSummary from '../../components/team/hours-report/HoursReportSummary.vue';
import HoursReportLoadState from '../../components/team/hours-report/HoursReportLoadState.vue';
import HoursReportTable from '../../components/team/hours-report/HoursReportTable.vue';
import { useHoursReportRequests } from '../../composables/useHoursReportRequests';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    groupOptions: { type: Array, default: () => [] },
});
const page = usePage();
const eventId = computed(() => page.props.activeEvent.id);
const startDate = ref(props.filters.start_date ?? '');
const endDate = ref(props.filters.end_date ?? '');
const selectedGroup = ref(props.filters.group_id ?? '');
const groups = shallowRef(props.groupOptions);

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
    if (Object.keys(rangeErrors.value).length) return;
    const params = new URLSearchParams();
    if (selectedGroup.value !== '') params.set('group_id', selectedGroup.value);
    if (startDate.value) {
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
    const groupOptions = groups.value;
    router.replace({
        url,
        props: (current) => ({
            ...current,
            filters,
            groupOptions,
        }),
        preserveState: true,
        preserveScroll: true,
    });
};

watch([eventId, selectedGroup, startDate, endDate], syncAddress, {
    immediate: true,
});

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
                    :class="showingPeople && 'max-md:hidden'"
                >
                    {{
                        $t('team.reports.description', {
                            event: page.props.activeEvent.name,
                        })
                    }}
                </p>
            </div>
            <p class="border-b border-line text-sm font-semibold text-primary">
                <span class="inline-block border-b-2 border-primary px-1 pb-3">
                    {{ $t('team.reports.by_group') }}
                </span>
            </p>
            <HoursReportFilters
                v-model:group-id="selectedGroup"
                v-model:start-date="startDate"
                v-model:end-date="endDate"
                :groups="groups"
                :errors="errors"
            />
            <section
                :aria-label="
                    showingPeople
                        ? $t('team.reports.group_total', { name: groupName })
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
        </div>
    </AppLayout>
</template>
