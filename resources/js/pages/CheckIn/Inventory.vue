<script setup>
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';
import AppLayout from '../../layouts/AppLayout.vue';
import { Card, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Dialog } from '../../components/ui/dialog';
import InventoryHoldingsSection from '../../components/check-in/InventoryHoldingsSection.vue';
import InventoryStockTable from '../../components/check-in/InventoryStockTable.vue';
import InventoryMovementDialog from '../../components/check-in/InventoryMovementDialog.vue';

const props = defineProps({
    event: { type: Object, required: true },
    view: { type: String, required: true },
    canManage: { type: Boolean, default: false },
    canPull: { type: Boolean, default: false },
    canMove: { type: Boolean, default: false },
    canTakeBack: { type: Boolean, default: false },
    ownPersonId: { type: Number, default: null },
    items: { type: Array, required: true },
    ownItems: { type: Array, required: true },
    locations: { type: Array, required: true },
});
const holder = ref(null);
const movementHolder = ref(null);
const movementOpen = ref(false);
const mode = ref('move');
const version = ref(0);
const pageTitle = computed(() =>
    trans(props.view === 'mine' ? 'inventory.my_stock' : 'inventory.title'),
);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('check_in.title'), href: '/check-in' },
    { label: pageTitle.value },
]);
const openMovement = (value) => {
    mode.value = value;
    movementHolder.value = value === 'take_back' ? holder.value : null;
    if (value === 'take_back') holder.value = null;
    movementOpen.value = true;
};
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
                {{ $t('inventory.lead') }}
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
                            @click="openMovement('move')"
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
                v-else-if="canManage"
                class="space-y-5"
            >
                <div class="flex justify-end">
                    <Button
                        :disabled="!canPull || event.locked"
                        @click="openMovement('pull')"
                        >{{ $t('inventory.pull') }}</Button
                    >
                </div>
                <InventoryHoldingsSection
                    holder-type="person"
                    :items="items"
                    :version="version"
                    @select-holder="holder = $event"
                />
                <InventoryHoldingsSection
                    holder-type="location"
                    :items="items"
                    :version="version"
                    @select-holder="holder = $event"
                />
            </div>
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
                    <Button
                        v-if="holder.holder_type === 'person'"
                        :disabled="
                            !canTakeBack || event.locked || !holder.total
                        "
                        @click="openMovement('take_back')"
                        >{{ $t('inventory.take_back') }}</Button
                    >
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
                :holder="movementHolder"
                :own-person-id="ownPersonId"
                @saved="saved"
            />
        </div>
    </AppLayout>
</template>
