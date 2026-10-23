<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Card, CardTitle } from '../ui/card';
import { Badge } from '../ui/badge';
import { Button } from '../ui/button';
import { CustomDropdown } from '../ui/custom-dropdown';
import { DataTable } from '../ui/data-table';
import { EntitlementItemName } from '../ui/entitlement-labels';
import InventoryStockCount from './InventoryStockCount.vue';

const props = defineProps({
    items: { type: Array, required: true },
    scope: { type: Object, default: () => ({}) },
    locations: { type: Boolean, default: false },
    locked: { type: Boolean, default: false },
    canAdjust: { type: Boolean, default: false },
    version: { type: Number, default: 0 },
});
const emit = defineEmits(['give', 'take-back', 'adjust']);
const table = ref(null);
const catalogueKey = computed(() =>
    props.items.map((item) => item.id).join(','),
);
const visibleItems = ref(props.items.map((item) => item.id));
watch(catalogueKey, () => {
    visibleItems.value = props.items.map((item) => item.id);
});
const failed = ref(false);
const canAct = ref(false);
let sequence = 0;
const title = computed(() =>
    trans(
        props.locations ? 'inventory.locations_storage' : 'inventory.holdings',
    ),
);
const columns = computed(() => [
    {
        data: 'name',
        title: trans(
            props.locations
                ? 'inventory.columns.location'
                : 'inventory.columns.person',
        ),
        render: { display: '#personCell' },
    },
    ...props.items.map((item) => ({
        data: null,
        title: '',
        item,
        render: { display: '#itemCell' },
    })),
    ...(!props.locations
        ? [
              {
                  data: null,
                  title: '',
                  className: 'dt-actions',
                  render: { display: '#actionsCell' },
              },
          ]
        : []),
]);
const options = computed(() => ({
    serverSide: true,
    ordering: false,
    searching: false,
    lengthChange: false,
    pageLength: 25,
    layout: { topStart: null, topEnd: null },
    language: {
        emptyTable: trans(
            props.locations ? 'inventory.no_locations' : 'inventory.no_roster',
        ),
    },
}));
const ajax = async (data, callback) => {
    const request = ++sequence;
    const parameters = new URLSearchParams({
        draw: data.draw,
        start: data.start,
        length: data.length,
        ...props.scope,
    });
    try {
        const response = await fetch(
            `/check-in/inventory/${props.locations ? 'locations' : 'holdings'}?${parameters}`,
            { headers: { Accept: 'application/json' } },
        );
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (request !== sequence) return;
        failed.value = false;
        canAct.value = result.canAct === true;
        callback(result);
    } catch {
        if (request !== sequence) return;
        failed.value = true;
        callback({
            draw: data.draw,
            recordsTotal: 0,
            recordsFiltered: 0,
            data: [],
        });
    }
};
const count = (person, item) =>
    person.items.find((stock) => stock.id === item.id)?.balance ?? 0;
const hasStock = (person) => person.items.some((stock) => stock.balance > 0);
const visibility = () =>
    props.items.forEach((item, index) =>
        table.value?.setColumnsVisible(
            [index + 1],
            visibleItems.value.includes(item.id),
        ),
    );
watch(visibleItems, visibility);
watch(
    () => props.version,
    () => table.value?.reload(false),
);
onBeforeUnmount(() => {
    ++sequence;
});
</script>

<template>
    <Card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <CardTitle>{{ title }}</CardTitle>
            <Button
                v-if="locations"
                :disabled="locked || !canAdjust"
                @click="emit('adjust')"
                >{{ $t('inventory.adjust') }}</Button
            >
        </div>
        <CustomDropdown
            v-if="locations || items.length >= 10"
            v-model="visibleItems"
            :items="
                items.map((item) => ({
                    value: item.id,
                    title: item.name,
                    labels: item.labels ?? [],
                }))
            "
            :aria-label="$t('inventory.columns.items')"
            :placeholder="
                visibleItems.length === items.length
                    ? $t('inventory.all_items')
                    : $t('inventory.selected_items', {
                          count: visibleItems.length,
                          total: items.length,
                      })
            "
            :show-selected="false"
            class="mb-4 max-w-md"
            multiple
        />
        <p
            v-if="failed"
            class="mb-3 text-sm text-danger"
            role="alert"
        >
            {{ $t('inventory.holdings_error') }}
            <Button
                variant="ghost"
                @click="table?.reload(false)"
                >{{ $t('inventory.history.retry') }}</Button
            >
        </p>
        <DataTable
            :key="catalogueKey"
            ref="table"
            :ajax="ajax"
            :columns="columns"
            :options="options"
            mobile-layout="cards"
            sticky-columns
            @init="visibility"
        >
            <template #header="{ column }">
                <EntitlementItemName
                    v-if="column.item"
                    :name="column.item.name"
                    :labels="column.item.labels ?? []"
                    stacked
                />
            </template>
            <template #personCell="{ rowData }">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold">{{ rowData.name }}</span>
                    <Badge
                        v-if="rowData.inactive"
                        variant="warning"
                        pill
                        >{{ $t('inventory.inactive') }}</Badge
                    >
                    <Badge
                        v-if="rowData.is_supervisor"
                        pill
                        >{{ $t('inventory.supervisor') }}</Badge
                    >
                </div>
            </template>
            <template #itemCell="{ rowData, colIndex }">
                <div
                    class="flex items-center justify-between gap-3 md:justify-end"
                >
                    <EntitlementItemName
                        :name="items[colIndex - 1].name"
                        :labels="items[colIndex - 1].labels ?? []"
                        class="md:hidden"
                    />
                    <InventoryStockCount
                        :count="count(rowData, items[colIndex - 1])"
                        :level="
                            locations
                                ? null
                                : items[colIndex - 1].low_stock_level
                        "
                    />
                </div>
            </template>
            <template #actionsCell="{ rowData }">
                <div class="flex gap-2 md:justify-end">
                    <Button
                        class="max-md:flex-1"
                        :disabled="locked || !canAct || rowData.inactive"
                        @click="emit('give', rowData)"
                        >{{ $t('inventory.give') }}</Button
                    >
                    <Button
                        variant="outline"
                        class="max-md:flex-1"
                        :disabled="locked || !canAct || !hasStock(rowData)"
                        @click="emit('take-back', rowData)"
                        >{{ $t('inventory.take_back') }}</Button
                    >
                </div>
            </template>
        </DataTable>
    </Card>
</template>
