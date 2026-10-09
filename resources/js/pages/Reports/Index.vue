<script setup>
import { computed } from 'vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import AppLayout from '../../layouts/AppLayout.vue';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Card, CardTitle } from '../../components/ui/card';
import { DataTable } from '../../components/ui/data-table';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { Tooltip } from '../../components/ui/tooltip';
import { mealDateLabel } from '../../lib/mealDates';

const props = defineProps({ meals: { type: Object, required: true } });
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('reports.meals.page_title') },
]);
const rows = computed(() => props.meals.rows);
const dateLabel = (row) => mealDateLabel(row.date, getActiveLanguage());
const countLabel = (count) =>
    new Intl.NumberFormat(getActiveLanguage()).format(count);
const columns = computed(() => [
    {
        data: 'meal_name',
        title: trans('reports.meals.columns.meal_name'),
        render: { display: '#nameCell' },
    },
    {
        data: 'date',
        type: 'string',
        title: trans('reports.meals.columns.date'),
        render: { display: '#dateCell' },
    },
    {
        data: 'meal_type',
        title: trans('reports.meals.columns.meal_type'),
        render: { display: '#typeCell' },
    },
    ...['projected', 'used', 'remaining', 'extras', 'total'].map((count) => ({
        data: count,
        title: trans(`reports.meals.columns.${count}`),
        render: { display: `#${count}Cell` },
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
    columnDefs: [
        { targets: [3, 4, 5, 6, 7], className: 'text-right tabular-nums' },
    ],
};
</script>

<template>
    <AppLayout
        :title="$t('reports.meals.page_title')"
        :breadcrumbs="breadcrumbs"
        back-href="/dashboard"
        :back-label="$t('nav.home')"
    >
        <div class="container mx-auto space-y-6">
            <h1 class="text-2xl font-bold text-charcoal">
                {{ $t('reports.meals.page_title') }}
            </h1>
            <Card>
                <div
                    class="mb-4 flex flex-wrap items-start justify-between gap-4"
                >
                    <div>
                        <CardTitle>{{ $t('reports.meals.title') }}</CardTitle>
                        <p class="mt-1 text-sm text-muted">
                            {{ $t('reports.meals.hint') }}
                        </p>
                    </div>
                    <Tooltip
                        v-if="rows.length === 0"
                        :label="$t('reports.meals.export_empty')"
                    >
                        <Button
                            variant="outline"
                            disabled
                        >
                            <Icon
                                :name="['fas', 'download']"
                                size="sm"
                            />
                            {{ $t('reports.meals.export') }}
                        </Button>
                        <template #content>{{
                            $t('reports.meals.export_empty')
                        }}</template>
                    </Tooltip>
                    <Button
                        v-else
                        as="a"
                        href="/reports/meals/export"
                        variant="outline"
                    >
                        <Icon
                            :name="['fas', 'download']"
                            size="sm"
                        />
                        {{ $t('reports.meals.export') }}
                    </Button>
                </div>
                <EmptyState
                    v-if="rows.length === 0"
                    :title="$t('reports.meals.empty')"
                >
                    <template #icon>
                        <Icon :name="['fas', 'utensils']" />
                    </template>
                </EmptyState>
                <template v-else>
                    <DataTable
                        :data="rows"
                        :columns="columns"
                        :options="tableOptions"
                    >
                        <template #dateCell="{ rowData }">
                            <span class="font-semibold whitespace-nowrap">{{
                                dateLabel(rowData)
                            }}</span>
                        </template>
                        <template #nameCell="{ rowData }">{{
                            rowData.meal_name
                        }}</template>
                        <template #typeCell="{ rowData }">
                            <Badge pill>{{ rowData.meal_type }}</Badge>
                        </template>
                        <template #projectedCell="{ rowData }">{{
                            countLabel(rowData.projected)
                        }}</template>
                        <template #usedCell="{ rowData }">{{
                            countLabel(-rowData.used)
                        }}</template>
                        <template #remainingCell="{ rowData }">{{
                            countLabel(rowData.remaining)
                        }}</template>
                        <template #extrasCell="{ rowData }">{{
                            countLabel(-rowData.extras)
                        }}</template>
                        <template #totalCell="{ rowData }">{{
                            countLabel(rowData.total)
                        }}</template>
                    </DataTable>
                    <p class="mt-3 text-xs text-muted">
                        {{ $t('reports.meals.footnote') }}
                    </p>
                </template>
            </Card>
        </div>
    </AppLayout>
</template>
