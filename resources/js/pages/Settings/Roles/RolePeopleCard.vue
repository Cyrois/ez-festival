<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Card } from '../../../components/ui/card';
import { DataTable } from '../../../components/ui/data-table';
import { Icon } from '../../../components/ui/icon';
import { navigateDataTableRow } from '../../../lib/dataTableRowNavigation';

defineProps({ roleId: { type: Number, required: true } });
const personHref = (person) => `/settings/team/${person.id}`;
const columns = computed(() => [
    {
        data: 'name',
        name: 'name',
        title: trans('settings.team.columns.name'),
        render: { display: '#nameCell' },
    },
    {
        data: null,
        defaultContent: '',
        name: 'open',
        title: '',
        orderable: false,
        searchable: false,
        render: { display: '#openCell' },
    },
]);
const options = computed(() => ({
    serverSide: true,
    pageLength: 25,
    lengthChange: false,
    columnDefs: [{ targets: 1, className: 'text-right' }],
    createdRow: (row, person) => navigateDataTableRow(row, person, personHref),
    language: { emptyTable: trans('settings.roles.people.empty') },
}));
</script>

<template>
    <Card class="mt-4">
        <h2 class="mb-4 text-xl font-bold text-muted">
            {{ $t('settings.roles.people.title') }}
        </h2>
        <DataTable
            :ajax="`/settings/roles/${roleId}/people`"
            :columns="columns"
            :options="options"
        >
            <template #nameCell="{ rowData }">
                <Link
                    :href="personHref(rowData)"
                    class="font-semibold text-charcoal no-underline hover:text-primary hover:underline"
                >
                    {{ rowData.name }}
                </Link>
            </template>
            <template #openCell="{ rowData }">
                <Link
                    :href="personHref(rowData)"
                    class="inline-flex text-muted hover:text-primary"
                    :aria-label="$t('data_table.open', { name: rowData.name })"
                >
                    <Icon
                        :name="['fas', 'chevron-right']"
                        size="sm"
                    />
                </Link>
            </template>
        </DataTable>
    </Card>
</template>
