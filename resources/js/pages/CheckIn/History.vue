<script setup>
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import AppLayout from '../../layouts/AppLayout.vue';
import { Card } from '../../components/ui/card';
import InventoryHistoryTable from '../../components/check-in/InventoryHistoryTable.vue';

defineProps({
    event: { type: Object, required: true },
    items: { type: Array, required: true },
    movementTypes: { type: Array, required: true },
});
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('check_in.title'), href: '/check-in' },
    { label: trans('inventory.history.title') },
]);
</script>

<template>
    <AppLayout
        :title="$t('inventory.history.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <h1 class="m-0 text-2xl font-bold tracking-tight">
                {{ $t('inventory.history.title') }}
            </h1>
            <p class="mt-1 mb-5 text-sm text-muted">
                {{ $t('inventory.history.lead', { event: event.name }) }}
            </p>
            <Card>
                <InventoryHistoryTable
                    endpoint="/check-in/history/data"
                    options-endpoint="/check-in/history/options"
                    export-url="/check-in/history/export"
                    :timezone="event.timezone"
                    :items="items"
                    :movement-types="movementTypes"
                />
            </Card>
        </div>
    </AppLayout>
</template>
