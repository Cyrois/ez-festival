<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import AppLayout from '../../layouts/AppLayout.vue';
import { Tab, TabList, TabPanel, Tabs } from '../../components/ui/tabs';
import { Card, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { Dialog } from '../../components/ui/dialog';
import { DataTable } from '../../components/ui/data-table';
import InventoryStockTable from '../../components/check-in/InventoryStockTable.vue';
import InventoryMovementDialog from '../../components/check-in/InventoryMovementDialog.vue';
import { activateDataTableRow } from '../../lib/dataTableRowNavigation';

const props = defineProps({
    event: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    canPull: { type: Boolean, default: false },
    canReturn: { type: Boolean, default: false },
    items: { type: Array, required: true },
    ownItems: { type: Array, required: true },
    locations: { type: Array, required: true },
});
const activeView = ref('mine');
const search = ref('');
const item = ref('');
const holderType = ref('');
const holder = ref(null);
const movementOpen = ref(false);
const mode = ref('return');
const version = ref(0);
const holdingsTable = ref(null);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('check_in.title') },
]);
const changeTab = (tab) => {
    if (tab === 'people') router.get('/check-in');
};
const openMovement = (value) => {
    mode.value = value;
    movementOpen.value = true;
};
const saved = () => {
    version.value += 1;
};
const filtersChanged = () => holdingsTable.value?.reload();
const itemOptions = computed(() => [
    { value: '', title: trans('inventory.all_items') },
    ...props.items.map((item) => ({ value: item.id, title: item.name })),
]);
const typeOptions = computed(() =>
    ['all', 'location', 'person'].map((value) => ({
        value: value === 'all' ? '' : value,
        title: trans(`inventory.holder_types.${value}`),
    })),
);
const columns = computed(() => [
    {
        data: 'name',
        title: trans('inventory.columns.holder'),
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
            holder.value = data;
        }),
    language: { emptyTable: trans('inventory.no_holdings') },
}));
const holdingsAjax = {
    url: '/check-in/inventory/holdings',
    data: (data) => {
        data.search = search.value;
        data.item = item.value || null;
        data.holder_type = holderType.value || null;
    },
};
</script>

<template>
    <AppLayout
        :title="$t('check_in.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <h1 class="m-0 text-2xl font-bold tracking-tight">
                {{ $t('check_in.title') }}
            </h1>
            <p class="mt-1 mb-5 text-sm text-muted">
                {{ $t('inventory.lead') }}
            </p>
            <Tabs
                model-value="inventory"
                @update:model-value="changeTab"
            >
                <TabList :aria-label="$t('check_in.title')">
                    <Tab value="people">{{ $t('inventory.people') }}</Tab>
                    <Tab value="inventory">{{ $t('inventory.title') }}</Tab>
                </TabList>
                <TabPanel value="people" />
                <TabPanel value="inventory">
                    <p
                        v-if="event.locked"
                        class="mb-4 text-sm text-muted"
                    >
                        {{ $t('inventory.locked') }}
                    </p>
                    <Tabs v-model="activeView">
                        <TabList
                            v-if="canManage"
                            :aria-label="$t('inventory.title')"
                        >
                            <Tab value="mine">{{
                                $t('inventory.my_stock')
                            }}</Tab>
                            <Tab value="holdings">{{
                                $t('inventory.holdings')
                            }}</Tab>
                        </TabList>
                        <component
                            :is="canManage ? TabPanel : 'div'"
                            :value="canManage ? 'mine' : undefined"
                        >
                            <Card>
                                <div
                                    class="mb-4 flex flex-wrap items-center justify-between gap-3"
                                >
                                    <CardTitle>{{
                                        $t('inventory.my_stock')
                                    }}</CardTitle>
                                    <Button
                                        :disabled="
                                            !canReturn ||
                                            !ownItems.length ||
                                            event.locked
                                        "
                                        @click="openMovement('return')"
                                        >{{ $t('inventory.return') }}</Button
                                    >
                                </div>
                                <p
                                    v-if="
                                        !canReturn &&
                                        !event.locked &&
                                        ownItems.length
                                    "
                                    class="text-sm text-muted"
                                >
                                    {{ $t('inventory.inactive_waiting') }}
                                </p>
                                <InventoryStockTable
                                    :key="`mine-${version}`"
                                    endpoint="/check-in/inventory/stock"
                                    :timezone="event.timezone"
                                />
                            </Card>
                            <Card class="mt-5">
                                <CardTitle class="mb-4">{{
                                    $t('inventory.recent_movements')
                                }}</CardTitle>
                                <InventoryStockTable
                                    :key="`history-${version}`"
                                    endpoint="/check-in/inventory/movements"
                                    :timezone="event.timezone"
                                    history
                                />
                            </Card>
                        </component>
                        <TabPanel
                            v-if="canManage"
                            value="holdings"
                        >
                            <Card>
                                <div
                                    class="mb-4 flex flex-wrap items-center justify-between gap-3"
                                >
                                    <CardTitle>{{
                                        $t('inventory.holdings')
                                    }}</CardTitle>
                                    <Button
                                        :disabled="!canPull || event.locked"
                                        @click="openMovement('pull')"
                                        >{{ $t('inventory.pull') }}</Button
                                    >
                                </div>
                                <div class="mb-4 grid gap-3 md:grid-cols-3">
                                    <Input
                                        v-model="search"
                                        type="search"
                                        :placeholder="
                                            $t('inventory.search_people')
                                        "
                                        :aria-label="
                                            $t('inventory.search_people')
                                        "
                                        @input="filtersChanged"
                                    />
                                    <CustomDropdown
                                        v-model="item"
                                        :items="itemOptions"
                                        :aria-label="
                                            $t('inventory.columns.item')
                                        "
                                        @update:model-value="filtersChanged"
                                    />
                                    <CustomDropdown
                                        v-model="holderType"
                                        :items="typeOptions"
                                        :aria-label="
                                            $t('inventory.columns.holder_type')
                                        "
                                        @update:model-value="filtersChanged"
                                    />
                                </div>
                                <DataTable
                                    :key="`holdings-${version}`"
                                    ref="holdingsTable"
                                    :ajax="holdingsAjax"
                                    :columns="columns"
                                    :options="holdingsOptions"
                                >
                                    <template #holderCell="{ rowData }">
                                        <span class="font-medium">{{
                                            rowData.name
                                        }}</span>
                                        <span
                                            class="block text-xs text-muted"
                                            >{{
                                                $t(
                                                    `inventory.holder_types.${rowData.holder_type}`,
                                                )
                                            }}</span
                                        >
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
                                            @click="holder = rowData"
                                        >
                                            <Icon
                                                :name="['fas', 'chevron-right']"
                                            />
                                        </button>
                                    </template>
                                </DataTable>
                            </Card>
                        </TabPanel>
                    </Tabs>
                </TabPanel>
            </Tabs>
            <Dialog
                :open="holder !== null"
                :title="holder?.name ?? ''"
                :show-confirm="false"
                :cancel-label="$t('inventory.close')"
                class="max-w-5xl"
                role="dialog"
                focus-trap
                @update:open="holder = null"
            >
                <div
                    v-if="holder"
                    class="mt-4 space-y-5"
                >
                    <p
                        v-if="holder.inactive"
                        class="text-sm text-muted"
                    >
                        {{ $t('inventory.inactive_waiting') }}
                    </p>
                    <InventoryStockTable
                        :key="`${holder.id}-stock-${version}`"
                        :endpoint="`/check-in/inventory/holders/${holder.id}/stock`"
                        :timezone="event.timezone"
                    />
                    <InventoryStockTable
                        :key="`${holder.id}-history-${version}`"
                        :endpoint="`/check-in/inventory/holders/${holder.id}/movements`"
                        :timezone="event.timezone"
                        history
                    />
                </div>
            </Dialog>
            <InventoryMovementDialog
                v-model:open="movementOpen"
                :mode="mode"
                :event-id="event.id"
                :items="items"
                :own-items="ownItems"
                :locations="locations"
                @saved="saved"
            />
        </div>
    </AppLayout>
</template>
