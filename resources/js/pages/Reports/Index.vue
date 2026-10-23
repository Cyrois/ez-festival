<script setup>
import { computed } from 'vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import AppLayout from '../../layouts/AppLayout.vue';
import { Badge } from '../../components/ui/badge';
import { Card, CardTitle } from '../../components/ui/card';
import { DataTable } from '../../components/ui/data-table';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import MealReportCounts from '../../components/reports/MealReportCounts.vue';
import { mealDateLabel } from '../../lib/mealDates';
import { mealReportCounts, mealReportCountLabel } from '../../lib/mealReport';

const props = defineProps({ meals: { type: Object, required: true } });
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('reports.meals.page_title') },
]);
const days = computed(() => props.meals.days);
const dateLabel = (date) => mealDateLabel(date, getActiveLanguage());
const mealCountLabel = (count) =>
    trans(`reports.meals.meal_count_${count === 1 ? 'one' : 'many'}`, {
        count,
    });
const rows = computed(() =>
    days.value.flatMap((day) => [
        ...day.meal_types.flatMap((group) => [
            {
                ...group.totals,
                date: day.date,
                kind: 'meal_type',
                meal_name: group.meal_type,
                meal_type: '',
                meal_count: group.meals.length,
            },
            ...group.meals.map((meal) => ({ ...meal, kind: 'meal' })),
        ]),
        {
            ...day.totals,
            date: day.date,
            kind: 'day_total',
            meal_name: trans('reports.meals.day_total'),
            meal_type: '',
        },
    ]),
);
const columns = computed(() => [
    {
        data: 'meal_name',
        title: trans('reports.meals.columns.meal_name'),
        render: { display: '#nameCell' },
    },
    {
        data: 'meal_type',
        title: trans('reports.meals.columns.meal_type'),
        render: { display: '#typeCell' },
    },
    ...mealReportCounts.map((key) => ({
        data: key,
        title: trans(`reports.meals.columns.${key}`),
        render: { display: `#${key}Cell` },
        className: 'text-right tabular-nums',
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
    createdRow: (row, data) => {
        row.dataset.mealReportKind = data.kind;
        if (data.kind === 'meal_type')
            row.classList.add('bg-page/50', 'font-semibold');
        if (data.kind === 'day_total')
            row.classList.add('border-t-2', 'border-line', 'font-semibold');
    },
    drawCallback: function () {
        const table = this.api();
        // Recreate presentation-only headings after each draw; meal data stays in DataTables.
        table
            .table()
            .body()
            .querySelectorAll('[data-meal-report-day]')
            .forEach((row) => row.remove());
        let previousDate;
        table.rows({ page: 'current' }).every(function () {
            const { date } = this.data();
            if (date === previousDate) return;
            const heading = document.createElement('tr');
            heading.dataset.mealReportDay = date;
            const cell = document.createElement('th');
            cell.colSpan = columns.value.length;
            cell.scope = 'rowgroup';
            cell.className =
                'bg-page px-4 py-4 text-left font-semibold text-charcoal';
            cell.textContent = dateLabel(date);
            heading.append(cell);
            this.node().before(heading);
            previousDate = date;
        });
    },
};
</script>

<template>
    <AppLayout
        :title="$t('reports.meals.page_title')"
        :breadcrumbs="breadcrumbs"
        compact-mobile-header
    >
        <div class="container mx-auto space-y-6">
            <h1 class="text-2xl font-bold text-charcoal">
                {{ $t('reports.meals.page_title') }}
            </h1>
            <Card>
                <div class="mb-4">
                    <CardTitle>{{ $t('reports.meals.title') }}</CardTitle>
                    <p class="mt-1 text-sm text-muted">
                        {{ $t('reports.meals.hint') }}
                    </p>
                </div>
                <EmptyState
                    v-if="days.length === 0"
                    :title="$t('reports.meals.empty')"
                >
                    <template #icon>
                        <Icon :name="['fas', 'utensils']" />
                    </template>
                </EmptyState>
                <template v-else>
                    <div class="hidden md:block">
                        <DataTable
                            :data="rows"
                            :columns="columns"
                            :options="tableOptions"
                        >
                            <template #nameCell="{ rowData }">
                                <span
                                    :class="{ 'pl-5': rowData.kind === 'meal' }"
                                >
                                    {{ rowData.meal_name }}
                                </span>
                                <span
                                    v-if="rowData.kind === 'meal_type'"
                                    class="ml-1 text-xs font-normal text-muted"
                                    >{{
                                        mealCountLabel(rowData.meal_count)
                                    }}</span
                                >
                            </template>
                            <template #typeCell="{ rowData }">
                                <Badge
                                    v-if="rowData.kind === 'meal'"
                                    pill
                                    >{{ rowData.meal_type }}</Badge
                                >
                            </template>
                            <template
                                v-for="key in mealReportCounts"
                                :key="key"
                                #[`${key}Cell`]="{ rowData }"
                                >{{
                                    mealReportCountLabel(rowData, key)
                                }}</template
                            >
                        </DataTable>
                    </div>
                    <div class="space-y-5 md:hidden">
                        <section
                            v-for="day in days"
                            :key="day.date"
                            :aria-labelledby="`meal-day-${day.date}`"
                            class="min-w-0 space-y-3"
                        >
                            <CardTitle
                                :id="`meal-day-${day.date}`"
                                as="h3"
                                >{{ dateLabel(day.date) }}</CardTitle
                            >
                            <Card
                                v-for="group in day.meal_types"
                                :key="group.meal_type_id"
                                class="overflow-hidden p-0"
                                data-meal-type-card
                            >
                                <div class="space-y-3 bg-page/50 p-3">
                                    <div
                                        class="flex flex-wrap items-baseline gap-1"
                                    >
                                        <CardTitle as="h4">{{
                                            group.meal_type
                                        }}</CardTitle>
                                        <span class="text-xs text-muted">{{
                                            mealCountLabel(group.meals.length)
                                        }}</span>
                                    </div>
                                    <MealReportCounts :counts="group.totals" />
                                </div>
                                <div
                                    v-for="meal in group.meals"
                                    :key="meal.meal_id"
                                    class="space-y-3 border-t border-line p-3"
                                    data-meal-detail
                                >
                                    <p class="text-sm font-medium break-words">
                                        {{ meal.meal_name }}
                                    </p>
                                    <MealReportCounts :counts="meal" />
                                </div>
                            </Card>
                            <Card
                                class="border-2 p-3"
                                data-day-total-card
                            >
                                <CardTitle as="h4">{{
                                    $t('reports.meals.day_total')
                                }}</CardTitle>
                                <MealReportCounts
                                    :counts="day.totals"
                                    class="mt-3"
                                />
                            </Card>
                        </section>
                    </div>
                    <p class="mt-3 text-xs text-muted">
                        {{ $t('reports.meals.footnote') }}
                    </p>
                </template>
            </Card>
        </div>
    </AppLayout>
</template>
