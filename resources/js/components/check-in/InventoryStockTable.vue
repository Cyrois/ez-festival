<script setup>
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { DataTable } from '../ui/data-table';

const props = defineProps({
    endpoint: { type: String, required: true },
    history: { type: Boolean, default: false },
    timezone: { type: String, required: true },
});
const columns = computed(() =>
    props.history
        ? [
              {
                  data: 'when',
                  title: trans('inventory.columns.when'),
                  render: { display: '#whenCell' },
              },
              {
                  data: 'item',
                  title: trans('inventory.columns.item'),
                  render: { display: '#itemCell' },
              },
              { data: 'quantity', title: trans('inventory.columns.quantity') },
              {
                  data: 'type',
                  title: trans('inventory.columns.movement'),
                  render: { display: '#typeCell' },
              },
              {
                  data: 'giver',
                  title: trans('inventory.columns.giver'),
                  render: { display: '#giverCell' },
              },
              {
                  data: 'receiver',
                  title: trans('inventory.columns.receiver'),
                  render: { display: '#receiverCell' },
              },
              {
                  data: 'recorded_by',
                  title: trans('inventory.columns.recorded_by'),
                  render: { display: '#recorded_byCell' },
              },
          ]
        : [
              {
                  data: 'name',
                  title: trans('inventory.columns.item'),
                  render: { display: '#nameCell' },
              },
              { data: 'balance', title: trans('inventory.columns.held') },
          ],
);
const options = computed(() => ({
    serverSide: true,
    ordering: false,
    searching: false,
    lengthChange: false,
    pageLength: 25,
    language: {
        emptyTable: trans(
            props.history ? 'inventory.no_movements' : 'inventory.no_stock',
        ),
    },
}));
const formatWhen = (value) =>
    new Intl.DateTimeFormat(undefined, {
        timeZone: props.timezone,
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
</script>

<template>
    <DataTable
        :ajax="{
            url: endpoint,
            data: (data) => {
                data.search = '';
            },
        }"
        :columns="columns"
        :options="options"
    >
        <template #itemCell="{ rowData }">{{ rowData.item }}</template>
        <template #nameCell="{ rowData }">{{ rowData.name }}</template>
        <template #giverCell="{ rowData }">{{ rowData.giver ?? '—' }}</template>
        <template #receiverCell="{ rowData }">{{
            rowData.receiver ?? '—'
        }}</template>
        <template #recorded_byCell="{ rowData }">{{
            rowData.recorded_by ?? '—'
        }}</template>
        <template #whenCell="{ rowData }">{{
            formatWhen(rowData.when)
        }}</template>
        <template #typeCell="{ rowData }">{{
            rowData.taken_from_me
                ? $t('inventory.taken_back_by', {
                      name:
                          rowData.recorded_by ?? $t('inventory.deleted_actor'),
                  })
                : $t(`inventory.movements.${rowData.type}`)
        }}</template>
    </DataTable>
</template>
