<script setup>
import { Badge } from '../ui/badge';
import { Card, CardTitle } from '../ui/card';
import { DataTable } from '../ui/data-table';
import { Icon } from '../ui/icon';
import { mealDateLabel, mealNextDayLabel } from '../../lib/mealDates';
import { computed } from 'vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';

defineProps({
    meals: { type: Object, required: true },
});

const dateLabel = (date) => mealDateLabel(date, getActiveLanguage());
const weekday = (date) =>
    mealDateLabel(date, getActiveLanguage(), {
        month: undefined,
        day: undefined,
    });
const nextDay = (date) => mealNextDayLabel(date, getActiveLanguage());
const shiftLabel = (row) =>
    trans('team.member.meals.shift', {
        location: row.shift_location,
        day: weekday(row.shift_start?.slice(0, 10)),
        start: row.shift_start?.slice(11),
        end: row.shift_end?.slice(11),
    });
const columns = computed(() => [
    {
        data: 'name',
        title: trans('meals.name'),
        render: { display: '#nameCell' },
    },
    {
        data: 'type',
        title: trans('meals.type'),
        render: { display: '#typeCell' },
    },
    {
        data: 'date',
        title: trans('team.member.meals.columns.date_time'),
        render: { display: '#dateCell' },
    },
    {
        data: 'shift_location',
        title: trans('meals.claim.columns.shift'),
        render: { display: '#shiftCell' },
    },
    {
        data: 'used',
        title: trans('meals.claim.columns.status'),
        render: { display: '#statusCell' },
    },
]);
const tableOptions = (day) => ({
    searching: false,
    ordering: false,
    lengthChange: false,
    info: false,
    pageLength: 10,
    layout: {
        topStart: null,
        topEnd: null,
        bottomStart: null,
        bottomEnd: day.rows.length > 10 ? 'paging' : null,
    },
});
</script>

<template>
    <Card id="meals">
        <CardTitle>{{ $t('team.member.sections.meals.title') }}</CardTitle>
        <p class="mt-1 mb-4 text-xs text-muted">
            {{ $t('team.member.sections.meals.description') }}
        </p>
        <p
            v-if="meals.days.length === 0"
            class="m-0 text-sm text-muted"
        >
            {{ $t('meals.list_empty') }}
        </p>
        <div
            v-for="day in meals.days"
            :key="day.date"
            class="mt-4"
        >
            <DataTable
                :key="day.rows.length > 10 ? 'paged' : 'all'"
                :data="day.rows"
                :columns="columns"
                :options="tableOptions(day)"
            >
                <template #nameCell="{ rowData }">
                    <span class="font-semibold">{{ rowData.name }}</span>
                </template>
                <template #typeCell="{ rowData }">
                    <Badge pill>{{ rowData.type }}</Badge>
                </template>
                <template #dateCell="{ rowData }">
                    <span class="whitespace-nowrap">
                        {{
                            $t('team.member.meals.date_time', {
                                date: dateLabel(rowData.date),
                                start: rowData.starts_at,
                                end: rowData.ends_at,
                            })
                        }}
                    </span>
                    <span
                        v-if="rowData.ends_at < rowData.starts_at"
                        class="ml-1 text-xs whitespace-nowrap text-muted"
                    >
                        ({{
                            $t('meals.ends_day', {
                                day: nextDay(rowData.date),
                            })
                        }})
                    </span>
                </template>
                <template #shiftCell="{ rowData }">
                    <span
                        v-if="rowData.source_removed"
                        class="text-muted italic"
                    >
                        {{ $t('team.member.meals.source_removed') }}
                    </span>
                    <span v-else-if="rowData.source_shift_id !== null">
                        {{ shiftLabel(rowData) }}
                    </span>
                    <span
                        v-else
                        class="text-muted"
                    >
                        {{ $t('meals.claim.direct_assignment') }}
                    </span>
                </template>
                <template #statusCell="{ rowData }">
                    <Badge
                        :variant="rowData.used ? 'success' : 'neutral'"
                        pill
                    >
                        <Icon
                            v-if="rowData.used"
                            :name="['fas', 'check']"
                            size="sm"
                            class="mr-1"
                        />
                        {{
                            rowData.used
                                ? $t('meals.claim.used', {
                                      time: rowData.used_at,
                                  })
                                : $t('meals.claim.not_used')
                        }}
                    </Badge>
                </template>
            </DataTable>
        </div>
    </Card>
</template>
