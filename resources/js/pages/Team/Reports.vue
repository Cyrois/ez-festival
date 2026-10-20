<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, shallowRef, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
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
const report = shallowRef(null);
const loading = ref(false);
const failed = ref(false);
const errors = ref({});
const loaded = new Map();
let controller;
let generation = 0;

const load = async () => {
    if (loading.value || Object.keys(errors.value).length) return;
    const requestedEvent = eventId.value;
    const key = JSON.stringify([
        requestedEvent,
        startDate.value,
        endDate.value,
    ]);
    failed.value = false;
    if (loaded.has(key)) {
        report.value = loaded.get(key);
        return;
    }
    controller = new AbortController();
    const number = generation;
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (startDate.value) {
            params.set('start_date', startDate.value);
            params.set('end_date', endDate.value);
        }
        const response = await fetch('/team/reports/data?' + params, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (number !== generation) return;
        if (response.status === 422) {
            const body = await response.json();
            if (number !== generation) return;
            errors.value = Object.fromEntries(
                Object.entries(body.errors ?? {}).map(([field, messages]) => [
                    field,
                    Array.isArray(messages) ? messages[0] : messages,
                ]),
            );
            showFormError(errors.value);
            return;
        }
        if (!response.ok) throw new Error('Hours report request failed');
        const { data } = await response.json();
        if (number !== generation) return;
        if (data.event_id !== requestedEvent)
            throw new Error('Hours report event changed');
        loaded.set(key, data);
        report.value = data;
    } catch (error) {
        if (number === generation && error.name !== 'AbortError')
            failed.value = true;
    } finally {
        if (number === generation) loading.value = false;
    }
};

watch(
    [eventId, startDate, endDate],
    () => {
        generation++;
        controller?.abort();
        report.value = null;
        loading.value = false;
        failed.value = false;
        errors.value = {};
        if (startDate.value && !endDate.value)
            errors.value.end_date = trans('team.reports.range_required');
        else if (endDate.value && !startDate.value)
            errors.value.start_date = trans('team.reports.range_required');
        else if (startDate.value > endDate.value)
            errors.value.end_date = trans('team.reports.range_order');
        load();
    },
    { immediate: true },
);
onUnmounted(() => {
    generation++;
    controller?.abort();
});

const duration = (minutes) =>
    trans('team.reports.duration', {
        hours: Math.floor(minutes / 60),
        minutes: String(minutes % 60).padStart(2, '0'),
    });
const columns = computed(() => [
    {
        data: 'name',
        title: trans('team.reports.group'),
        render: { display: '#nameCell' },
    },
    ...['scheduled', 'approved', 'unapproved'].map((type) => ({
        data: `${type}_minutes`,
        title: trans(`team.reports.${type}`),
        render: { display: `#${type}Cell` },
    })),
]);
const tableOptions = {
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
    columnDefs: [{ targets: [1, 2, 3], className: 'text-right tabular-nums' }],
};
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
            <div>
                <div class="flex flex-wrap gap-3">
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
                :aria-label="$t('team.reports.event_total')"
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
                        {{ $t('team.reports.loading') }}
                    </p>
                </Card>
                <Card v-else-if="failed">
                    <div
                        role="alert"
                        class="flex flex-wrap items-center gap-3 text-sm text-danger"
                    >
                        <span>{{ $t('team.reports.load_failed') }}</span>
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
                        v-if="report.groups.length === 0"
                        :title="$t('team.reports.empty')"
                        :description="$t('team.reports.empty_description')"
                    />
                    <DataTable
                        v-else
                        :data="report.groups"
                        :columns="columns"
                        :options="tableOptions"
                    >
                        <template #nameCell="{ rowData }">
                            <span class="font-semibold">{{
                                rowData.name
                            }}</span>
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
                    </DataTable>
                </template>
            </section>
        </div>
    </AppLayout>
</template>
