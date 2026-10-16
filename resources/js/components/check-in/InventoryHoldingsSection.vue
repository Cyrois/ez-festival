<script setup>
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Card, CardTitle } from '../ui/card';
import { Badge } from '../ui/badge';
import { Icon } from '../ui/icon';
import { Input } from '../ui/input';
import { CustomDropdown } from '../ui/custom-dropdown';
import { DataTable } from '../ui/data-table';
import { activateDataTableRow } from '../../lib/dataTableRowNavigation';

const props = defineProps({
    holderType: { type: String, required: true },
    items: { type: Array, required: true },
    version: { type: Number, default: 0 },
});
const emit = defineEmits(['select-holder']);
const sectionTitle = computed(() =>
    trans(
        props.holderType === 'person'
            ? 'inventory.people'
            : 'inventory.locations_storage',
    ),
);
const search = ref('');
const item = ref('');
const holdingsTable = ref(null);
const filtersChanged = () => holdingsTable.value?.reload();
const itemOptions = computed(() => [
    { value: '', title: trans('inventory.all_items') },
    ...props.items.map((item) => ({ value: item.id, title: item.name })),
]);
const columns = computed(() => [
    {
        data: 'name',
        title: trans(
            `inventory.columns.${props.holderType === 'person' ? 'person' : 'location'}`,
        ),
        render: { display: '#holderCell' },
    },
    {
        data: 'items',
        title: trans('inventory.columns.items'),
        orderable: false,
        searchable: false,
        render: { display: '#itemsCell' },
    },
    { data: 'total', title: trans('inventory.columns.total') },
    {
        data: null,
        title: '',
        orderable: false,
        searchable: false,
        defaultContent: '',
        render: { display: '#openCell' },
    },
]);
const holdingsOptions = computed(() => ({
    serverSide: true,
    ordering: false,
    searching: false,
    lengthChange: false,
    pageLength: 25,
    columnDefs: [{ targets: 3, className: 'text-right', width: '1%' }],
    createdRow: (row, data) =>
        activateDataTableRow(row, data, (data) => {
            emit('select-holder', data);
        }),
    language: { emptyTable: trans('inventory.no_holdings') },
}));
const holdingsAjax = {
    url: '/check-in/inventory/holdings',
    data: (data) => {
        data.search = search.value;
        data.item = item.value || null;
        data.holder_type = props.holderType;
    },
};
</script>

<template>
    <Card
        role="region"
        :aria-label="sectionTitle"
    >
        <CardTitle class="mb-4">{{ sectionTitle }}</CardTitle>
        <div class="mb-4 grid gap-3 md:grid-cols-2">
            <Input
                v-if="holderType === 'person'"
                v-model="search"
                type="search"
                :placeholder="$t('inventory.search_people')"
                :aria-label="$t('inventory.search_people')"
                @update:model-value="filtersChanged"
            />
            <CustomDropdown
                v-model="item"
                :items="itemOptions"
                :aria-label="$t('inventory.columns.item')"
                @update:model-value="filtersChanged"
            />
        </div>
        <DataTable
            :key="`${holderType}-${version}`"
            ref="holdingsTable"
            :ajax="holdingsAjax"
            :columns="columns"
            :options="holdingsOptions"
        >
            <template #holderCell="{ rowData }">
                <span class="font-medium">{{ rowData.name }}</span>
                <Badge
                    v-if="rowData.inactive"
                    variant="warning"
                    pill
                    >{{
                        $t('inventory.inactive_holds', {
                            count: rowData.total,
                        })
                    }}</Badge
                >
            </template>
            <template #itemsCell="{ rowData }">
                <span
                    v-for="stock in rowData.items"
                    :key="stock.id"
                    class="block"
                    >{{
                        $t('inventory.item_count', {
                            item: stock.name,
                            count: stock.balance,
                        })
                    }}</span
                >
            </template>
            <template #openCell="{ rowData }">
                <button
                    type="button"
                    class="inline-flex text-muted hover:text-primary"
                    :aria-label="
                        $t('inventory.open_holder', {
                            name: rowData.name,
                        })
                    "
                    @click="emit('select-holder', rowData)"
                >
                    <Icon :name="['fas', 'chevron-right']" />
                </button>
            </template>
        </DataTable>
    </Card>
</template>
