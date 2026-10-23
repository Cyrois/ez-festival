<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import AppLayout from '../../layouts/AppLayout.vue';
import { Card, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { EmptyState } from '../../components/ui/empty-state';
import { FormField } from '../../components/ui/form-field';
import InventoryPicker from '../../components/check-in/InventoryPicker.vue';
import InventoryBatchDialog from '../../components/check-in/InventoryBatchDialog.vue';
import InventoryHoldingsSection from '../../components/check-in/InventoryHoldingsSection.vue';
import InventoryStockTable from '../../components/check-in/InventoryStockTable.vue';
import InventoryMovementDialog from '../../components/check-in/InventoryMovementDialog.vue';

const props = defineProps({
    event: { type: Object, required: true },
    view: { type: String, required: true },
    canManage: { type: Boolean, default: false },
    canAdjust: { type: Boolean, default: false },
    canMove: { type: Boolean, default: false },
    canReceive: { type: Boolean, default: false },
    ownPersonId: { type: Number, default: null },
    items: { type: Array, required: true },
    ownItems: { type: Array, required: true },
    locations: { type: Array, required: true },
});
const page = usePage();
const movementOpen = ref(false);
const batchOpen = ref(false);
const batchMode = ref('give');
const person = ref(null);
const shiftId = ref('');
const groupId = ref('');
const scope = computed(() =>
    shiftId.value
        ? { shift_id: shiftId.value }
        : groupId.value
          ? { group_id: groupId.value }
          : {},
);
const scopeKey = computed(() => JSON.stringify(scope.value));
const pickShift = (value) => {
    shiftId.value = value;
    groupId.value = '';
};
const pickGroup = (value) => {
    groupId.value = value;
    shiftId.value = '';
};
const openBatch = (mode, row = null) => {
    batchMode.value = mode;
    person.value = row;
    batchOpen.value = true;
};
const version = ref(0);
const pageTitle = computed(() =>
    trans(props.view === 'mine' ? 'inventory.my_stock' : 'inventory.title'),
);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    {
        label: trans('check_in.title'),
        href: page.props.permissions?.['checkin.view']
            ? '/check-in'
            : undefined,
    },
    { label: pageTitle.value },
]);
const saved = () => {
    version.value += 1;
};
</script>

<template>
    <AppLayout
        :title="pageTitle"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <h1 class="m-0 text-2xl font-bold tracking-tight">
                {{ pageTitle }}
            </h1>
            <p class="mt-1 mb-5 text-sm text-muted">
                {{
                    $t(
                        view === 'mine'
                            ? 'inventory.lead'
                            : 'inventory.holdings_lead',
                    )
                }}
            </p>
            <p
                v-if="event.locked"
                class="mb-4 text-sm text-muted"
            >
                {{ $t('inventory.locked') }}
            </p>
            <div v-if="view === 'mine'">
                <Card>
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-3"
                    >
                        <CardTitle>{{ $t('inventory.my_stock') }}</CardTitle>
                        <Button
                            :disabled="
                                !canMove || !ownItems.length || event.locked
                            "
                            @click="movementOpen = true"
                            >{{ $t('inventory.move') }}</Button
                        >
                    </div>
                    <p
                        v-if="!canMove && !event.locked && ownItems.length"
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
            </div>
            <div
                v-else
                class="space-y-5"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <FormField :label="$t('inventory.shift')">
                        <InventoryPicker
                            :model-value="shiftId"
                            endpoint="/check-in/inventory/scopes"
                            :parameters="{ type: 'shift' }"
                            :label="$t('inventory.choose_shift')"
                            shift
                            @update:model-value="pickShift"
                        />
                    </FormField>
                    <FormField
                        v-if="canManage"
                        :label="$t('inventory.team')"
                    >
                        <InventoryPicker
                            :model-value="groupId"
                            endpoint="/check-in/inventory/scopes"
                            :parameters="{ type: 'group' }"
                            :label="$t('inventory.choose_team')"
                            @update:model-value="pickGroup"
                        />
                    </FormField>
                </div>
                <InventoryHoldingsSection
                    v-if="shiftId || groupId"
                    :key="scopeKey"
                    :items="items"
                    :scope="scope"
                    :locked="event.locked"
                    :version="version"
                    @give="openBatch('give', $event)"
                    @take-back="openBatch('take_back', $event)"
                />
                <EmptyState
                    v-else
                    :title="
                        $t(
                            canManage
                                ? 'inventory.pick_roster'
                                : 'inventory.pick_shift',
                        )
                    "
                    :description="
                        $t(
                            canManage
                                ? 'inventory.pick_roster_lead'
                                : 'inventory.pick_shift_lead',
                        )
                    "
                />
                <InventoryHoldingsSection
                    v-if="canManage"
                    :items="items"
                    :version="version"
                    :locked="event.locked"
                    :can-adjust="canAdjust"
                    locations
                    @adjust="openBatch('adjust')"
                />
            </div>
            <InventoryBatchDialog
                v-model:open="batchOpen"
                :mode="batchMode"
                :person="person"
                :scope="scope"
                :event-id="event.id"
                :items="items"
                :own-items="ownItems"
                :locations="locations"
                :can-manage="canAdjust"
                :can-receive="canReceive"
                :own-person-id="ownPersonId"
                @saved="saved"
            />
            <InventoryMovementDialog
                v-model:open="movementOpen"
                :event-id="event.id"
                :own-items="ownItems"
                :locations="locations"
                @saved="saved"
            />
        </div>
    </AppLayout>
</template>
