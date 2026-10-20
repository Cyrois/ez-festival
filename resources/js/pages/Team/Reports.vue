<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import {
    computed,
    onUnmounted,
    ref,
    shallowReactive,
    shallowRef,
    watch,
} from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Badge } from '../../components/ui/badge';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { Icon } from '../../components/ui/icon';
import { activateDataTableRow } from '../../lib/dataTableRowNavigation';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { DataTable } from '../../components/ui/data-table';
import { EmptyState } from '../../components/ui/empty-state';
import { FormField } from '../../components/ui/form-field';
import { Input } from '../../components/ui/input';
import { useFlashToast } from '../../composables/useFlashToast';

const page = usePage();
const { showFormError } = useFlashToast();
const eventId = computed(() => page.props.activeEvent.id);
const startDate = ref('');
const endDate = ref('');
const selectedGroup = ref('');
const groups = shallowRef([]);
// Both overview and complete-group responses live only for this page visit.
const requests = shallowReactive(new Map());
const key = computed(() =>
    JSON.stringify([
        eventId.value,
        selectedGroup.value,
        startDate.value,
        endDate.value,
    ]),
);
const current = computed(() => requests.get(key.value));
const report = computed(() => current.value?.data ?? null);
const loading = computed(() => current.value?.status === 'loading');
const failed = computed(() => current.value?.status === 'failed');
const showingPeople = computed(() => selectedGroup.value !== '');
const rangeErrors = computed(() => {
    if (startDate.value && !endDate.value)
        return { end_date: trans('team.reports.range_required') };
    if (endDate.value && !startDate.value)
        return { start_date: trans('team.reports.range_required') };
    if (startDate.value > endDate.value)
        return { end_date: trans('team.reports.range_order') };
    return {};
});
const errors = computed(() => ({
    ...rangeErrors.value,
    ...current.value?.errors,
}));
const rows = computed(
    () =>
        (showingPeople.value ? report.value?.people : report.value?.groups) ??
        [],
);
const groupItems = computed(() =>
    groups.value.map((group) => ({ value: group.group_id, title: group.name })),
);
const groupName = computed(
    () =>
        groups.value.find((group) => group.group_id === selectedGroup.value)
            ?.name ?? '',
);
let mounted = true;

const cancelPending = () => {
    for (const [requestKey, request] of requests) {
        if (request.status === 'loading') {
            requests.delete(requestKey);
            request.controller.abort();
        }
    }
};
const load = async () => {
    if (
        Object.keys(rangeErrors.value).length ||
        ['loading', 'loaded'].includes(current.value?.status)
    )
        return;
    const requestKey = key.value;
    const requestedEvent = eventId.value;
    const requestedGroup = selectedGroup.value;
    const requestedStart = startDate.value || null;
    const requestedEnd = endDate.value || null;
    const entry = { status: 'loading', controller: new AbortController() };
    requests.set(requestKey, entry);
    const isCurrentRequest = () =>
        mounted && requests.get(requestKey) === entry;
    try {
        const params = new URLSearchParams();
        if (requestedStart) {
            params.set('start_date', requestedStart);
            params.set('end_date', requestedEnd);
        }
        const endpoint =
            requestedGroup === ''
                ? '/team/reports/data'
                : `/team/reports/groups/${requestedGroup}/people`;
        const response = await fetch(endpoint + '?' + params, {
            headers: { Accept: 'application/json' },
            signal: entry.controller.signal,
        });
        if (!isCurrentRequest()) return;
        if (response.status === 422) {
            const body = await response.json();
            if (!isCurrentRequest()) return;
            const validation = Object.fromEntries(
                Object.entries(body.errors ?? {}).map(([field, messages]) => [
                    field,
                    Array.isArray(messages) ? messages[0] : messages,
                ]),
            );
            requests.set(requestKey, { status: 'invalid', errors: validation });
            if (key.value === requestKey) showFormError(validation);
            return;
        }
        if (!response.ok) throw new Error('Hours report request failed');
        const { data } = await response.json();
        if (!isCurrentRequest()) return;
        if (
            data.event_id !== requestedEvent ||
            data.range.start_date !== requestedStart ||
            data.range.end_date !== requestedEnd ||
            (requestedGroup !== '' && data.group.group_id !== requestedGroup)
        )
            throw new Error('Hours report selection changed');
        requests.set(requestKey, { status: 'loaded', data });
        if (requestedGroup === '') groups.value = data.groups;
    } catch (error) {
        if (isCurrentRequest() && error.name !== 'AbortError')
            requests.set(requestKey, { status: 'failed' });
    }
};

watch(
    [eventId, selectedGroup, startDate, endDate],
    (values, previous) => {
        if (previous.length && values[0] !== previous[0]) {
            cancelPending();
            requests.clear();
            groups.value = [];
            selectedGroup.value = '';
        } else if (
            previous.length &&
            (values[2] !== previous[2] || values[3] !== previous[3])
        ) {
            cancelPending();
        }
        load();
    },
    { immediate: true },
);
onUnmounted(() => {
    mounted = false;
    cancelPending();
    requests.clear();
});

const duration = (minutes) =>
    trans('team.reports.duration', {
        hours: Math.floor(minutes / 60),
        minutes: String(minutes % 60).padStart(2, '0'),
    });
const columns = computed(() => [
    {
        data: 'name',
        title: trans(
            showingPeople.value ? 'team.reports.person' : 'team.reports.group',
        ),
        render: { display: '#nameCell' },
    },
    ...['scheduled', 'approved', 'unapproved'].map((type) => ({
        data: `${type}_minutes`,
        title: trans(`team.reports.${type}`),
        render: { display: `#${type}Cell` },
    })),
    ...(showingPeople.value
        ? []
        : [
              {
                  data: null,
                  title: '',
                  render: { display: '#groupActionCell' },
                  defaultContent: '',
              },
          ]),
]);
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
        { targets: [1, 2, 3], className: 'text-right tabular-nums' },
        ...(!showingPeople.value
            ? [{ targets: 4, className: 'w-12 text-right' }]
            : []),
    ],
    createdRow: showingPeople.value
        ? undefined
        : (row, group) =>
              activateDataTableRow(row, group, (item) => {
                  selectedGroup.value = item.group_id;
              }),
}));
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
                <p class="mt-1 text-sm text-muted">
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
            <Button
                v-if="showingPeople"
                variant="ghost"
                class="px-0 text-muted"
                @click="selectedGroup = ''"
            >
                <Icon :name="['fas', 'arrow-left']" />
                {{ $t('team.reports.back') }}
            </Button>
            <div>
                <div class="flex flex-wrap gap-3">
                    <FormField
                        v-if="showingPeople"
                        v-slot="{ id }"
                        :label="$t('team.reports.group_picker')"
                        html-for="hours-group"
                        class="w-64"
                    >
                        <CustomDropdown
                            :id="id"
                            v-model="selectedGroup"
                            :items="groupItems"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        :label="$t('team.reports.start_date')"
                        html-for="hours-start-date"
                        :error="errors.start_date"
                        class="w-44"
                    >
                        <Input
                            :id="id"
                            v-model="startDate"
                            type="date"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        :label="$t('team.reports.end_date')"
                        html-for="hours-end-date"
                        :error="errors.end_date"
                        class="w-44"
                    >
                        <Input
                            :id="id"
                            v-model="endDate"
                            type="date"
                            :invalid="invalid"
                        />
                    </FormField>
                </div>
                <p class="mt-3 text-xs text-muted">
                    {{ $t('team.reports.range_hint') }}
                </p>
            </div>
            <section
                :aria-label="
                    showingPeople
                        ? $t('team.reports.group_total', { name: groupName })
                        : $t('team.reports.event_total')
                "
                :aria-busy="loading"
                class="space-y-5"
            >
                <dl class="grid gap-3 md:grid-cols-3">
                    <Card
                        v-for="type in ['scheduled', 'approved', 'unapproved']"
                        :key="type"
                    >
                        <dt class="text-xs font-semibold text-muted uppercase">
                            {{ $t(`team.reports.${type}`) }}
                        </dt>
                        <dd class="mt-1 text-2xl font-bold tabular-nums">
                            <span
                                v-if="loading && type === 'scheduled'"
                                class="block h-8 w-28 animate-pulse rounded bg-line"
                                aria-hidden="true"
                            />
                            <span v-else-if="report && type === 'scheduled'">{{
                                duration(report.totals.scheduled_minutes)
                            }}</span>
                            <span
                                v-else
                                class="text-muted"
                                >{{ $t('team.reports.unavailable') }}</span
                            >
                        </dd>
                    </Card>
                </dl>
                <Card v-if="loading">
                    <div aria-hidden="true">
                        <div
                            v-for="row in 5"
                            :key="row"
                            class="grid grid-cols-4 gap-6 border-b border-line py-5 last:border-0"
                        >
                            <span
                                v-for="column in 4"
                                :key="column"
                                class="h-3 animate-pulse rounded bg-line"
                            />
                        </div>
                    </div>
                    <p
                        role="status"
                        class="mt-3 text-sm text-muted"
                    >
                        {{
                            $t(
                                showingPeople
                                    ? 'team.reports.people_loading'
                                    : 'team.reports.loading',
                            )
                        }}
                    </p>
                </Card>
                <Card v-else-if="failed">
                    <div
                        role="alert"
                        class="flex flex-wrap items-center gap-3 text-sm text-danger"
                    >
                        <span>{{
                            $t(
                                showingPeople
                                    ? 'team.reports.people_load_failed'
                                    : 'team.reports.load_failed',
                            )
                        }}</span>
                        <Button
                            variant="outline"
                            :loading="loading"
                            @click="load"
                            >{{ $t('team.reports.retry') }}</Button
                        >
                    </div>
                </Card>
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
                    <DataTable
                        v-else
                        :key="key"
                        :data="rows"
                        :columns="columns"
                        :options="tableOptions"
                    >
                        <template #nameCell="{ rowData }">
                            <span
                                class="inline-flex flex-wrap items-center gap-2"
                            >
                                <span class="font-semibold">{{
                                    rowData.name
                                }}</span>
                                <Badge
                                    v-if="showingPeople"
                                    pill
                                    :class="
                                        rowData.employment_type === 'paid'
                                            ? 'gap-1 border-secondary/20 bg-secondary/10 text-secondary'
                                            : 'gap-1 text-muted'
                                    "
                                >
                                    <Icon
                                        :name="['fas', 'circle']"
                                        class="text-[6px]"
                                    />
                                    {{
                                        $t(
                                            `team.advancement.employment_type.${rowData.employment_type}`,
                                        )
                                    }}
                                </Badge>
                            </span>
                        </template>
                        <template #scheduledCell="{ rowData }">{{
                            duration(rowData.scheduled_minutes)
                        }}</template>
                        <template #approvedCell>
                            <span class="text-muted">{{
                                $t('team.reports.unavailable')
                            }}</span>
                        </template>
                        <template #unapprovedCell>
                            <span class="text-muted">{{
                                $t('team.reports.unavailable')
                            }}</span>
                        </template>
                        <template #groupActionCell="{ rowData }">
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="
                                    $t('team.reports.view_group', {
                                        name: rowData.name,
                                    })
                                "
                                @click="selectedGroup = rowData.group_id"
                            >
                                <Icon :name="['fas', 'chevron-right']" />
                            </Button>
                        </template>
                    </DataTable>
                </template>
            </section>
        </div>
    </AppLayout>
</template>
