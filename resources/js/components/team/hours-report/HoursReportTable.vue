<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Badge } from '../../ui/badge';
import { Button } from '../../ui/button';
import { DataTable } from '../../ui/data-table';
import { Icon } from '../../ui/icon';
import {
    activateDataTableRow,
    navigateDataTableRow,
} from '../../../lib/dataTableRowNavigation';
import { formatHoursReportDuration } from '../../../lib/hoursReport';

const props = defineProps({
    rows: { type: Array, required: true },
    requestKey: { type: String, required: true },
    showingPeople: { type: Boolean, default: false },
    canViewTeam: { type: Boolean, default: false },
    shiftPeople: { type: Boolean, default: false },
    columnWidths: { type: Array, default: () => [] },
});
const emit = defineEmits(['select-group']);
const hasRowAction = computed(() => !props.showingPeople || props.canViewTeam);
const hasTrailingColumn = computed(
    () => hasRowAction.value || props.shiftPeople,
);
const columns = computed(() => [
    {
        data: 'name',
        title: trans(
            props.showingPeople ? 'team.reports.person' : 'team.reports.group',
        ),
        render: { display: '#nameCell' },
    },
    ...['scheduled', 'approved', 'unapproved'].map((type) => ({
        data: `${type}_minutes`,
        title: trans(`team.reports.${type}`),
        render: { display: `#${type}Cell` },
    })),
    ...(!hasTrailingColumn.value
        ? []
        : [
              {
                  data: null,
                  title: '',
                  render: { display: '#actionCell' },
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
        ...(props.shiftPeople ? [{ targets: 0, className: 'md:pl-10' }] : []),
        { targets: [1, 2, 3], className: 'text-right tabular-nums' },
        ...(hasTrailingColumn.value
            ? [{ targets: 4, className: 'dt-row-action w-12 text-right' }]
            : []),
    ],
    createdRow: props.showingPeople
        ? props.canViewTeam
            ? (row, person) => {
                  if (person.member_url)
                      navigateDataTableRow(
                          row,
                          person,
                          (item) => item.member_url,
                          { newTab: true },
                      );
              }
            : undefined
        : (row, group) =>
              activateDataTableRow(row, group, (item) => {
                  emit('select-group', item.group_id);
              }),
}));
</script>

<template>
    <div class="space-y-2">
        <p
            v-if="showingPeople && canViewTeam && !shiftPeople"
            class="text-xs text-muted"
        >
            {{ $t('team.reports.member_hint') }}
        </p>
        <DataTable
            :key="requestKey"
            :data="rows"
            :columns="columns"
            :options="tableOptions"
            :embedded="shiftPeople"
            :column-widths="columnWidths"
            :mobile-layout="showingPeople ? 'summary' : 'stacked'"
        >
            <template #nameCell="{ rowData }">
                <span class="inline-flex flex-wrap items-center gap-2">
                    <Link
                        v-if="
                            showingPeople && canViewTeam && rowData.member_url
                        "
                        :href="rowData.member_url"
                        target="_blank"
                        rel="noopener"
                        :aria-label="
                            $t('team.reports.view_member', {
                                name: rowData.name,
                            })
                        "
                        class="font-semibold text-primary underline underline-offset-2"
                        >{{ rowData.name }}</Link
                    >
                    <span
                        v-else
                        class="font-semibold"
                        >{{ rowData.name }}</span
                    >
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
                    <Badge
                        v-if="shiftPeople && rowData.is_supervisor"
                        pill
                        class="border-primary/20 bg-primary/10 text-primary"
                    >
                        {{ $t('team.scheduling.supervisor.tag') }}
                    </Badge>
                </span>
            </template>
            <template #scheduledCell="{ rowData }">{{
                formatHoursReportDuration(rowData.scheduled_minutes)
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
            <template #actionCell="{ rowData }">
                <Link
                    v-if="showingPeople && canViewTeam && rowData.member_url"
                    :href="rowData.member_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-primary focus-visible:outline-primary"
                    :aria-label="
                        $t('team.reports.view_member', {
                            name: rowData.name,
                        })
                    "
                >
                    <Icon :name="['fas', 'chevron-right']" />
                </Link>
                <Button
                    v-else-if="!showingPeople"
                    variant="ghost"
                    size="icon"
                    :aria-label="
                        $t('team.reports.view_group', {
                            name: rowData.name,
                        })
                    "
                    @click="emit('select-group', rowData.group_id)"
                >
                    <Icon :name="['fas', 'chevron-right']" />
                </Button>
            </template>
        </DataTable>
    </div>
</template>
