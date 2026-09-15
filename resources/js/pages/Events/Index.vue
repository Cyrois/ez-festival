<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Dialog } from '../../components/ui/dialog';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../../components/ui/table';
import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

const lockOpen = ref(false);
const lockBusy = ref(false);
const lockingEvent = ref(null);

const openLock = (event) => {
    lockingEvent.value = event;
    lockOpen.value = true;
};

const confirmLock = () => {
    if (!lockingEvent.value || lockBusy.value) {
        return;
    }

    lockBusy.value = true;
    router.post(
        `/events/${lockingEvent.value.id}/lock`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                lockBusy.value = false;
                lockOpen.value = false;
                lockingEvent.value = null;
            },
        },
    );
};

const statusLabel = (event) => {
    if (event.is_locked) {
        return trans('events.status.locked');
    }

    if (event.is_active) {
        return trans('events.status.active');
    }

    return trans('events.status.open');
};

const statusVariant = (event) => {
    if (event.is_locked) {
        return 'warning';
    }

    if (event.is_active) {
        return 'primary';
    }

    return 'neutral';
};

const formatDates = (event) => `${event.starts_on} – ${event.ends_on}`;
</script>

<template>
    <AppLayout :title="$t('events.title')">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('events.title') }}
                </h1>
            </div>
        </div>

        <EmptyState
            v-if="events.length === 0"
            :title="$t('events.empty.title')"
            :description="$t('events.empty.body')"
        >
            <template #icon>
                <Icon
                    :name="['fas', 'calendar-days']"
                    size="lg"
                />
            </template>
        </EmptyState>

        <div
            v-else
            class="overflow-hidden rounded-xl border border-line bg-ground"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>
                            {{ $t('events.columns.name') }}
                        </TableHead>
                        <TableHead>
                            {{ $t('events.columns.dates') }}
                        </TableHead>
                        <TableHead>
                            {{ $t('events.columns.timezone') }}
                        </TableHead>
                        <TableHead>
                            {{ $t('events.columns.status') }}
                        </TableHead>
                        <TableHead class="text-right">
                            {{ $t('events.columns.actions') }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="event in events"
                        :key="event.id"
                    >
                        <TableCell class="font-semibold">
                            {{ event.name }}
                        </TableCell>
                        <TableCell class="text-muted">
                            {{ formatDates(event) }}
                        </TableCell>
                        <TableCell class="text-muted">
                            {{ event.timezone }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                :variant="statusVariant(event)"
                                pill
                            >
                                {{ statusLabel(event) }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-2">
                                <Button
                                    :href="`/events/${event.id}`"
                                    variant="outline"
                                    size="sm"
                                >
                                    {{ $t('events.actions.view') }}
                                </Button>
                                <Button
                                    v-if="!event.is_locked"
                                    variant="secondary"
                                    size="sm"
                                    @click="openLock(event)"
                                >
                                    {{ $t('events.actions.lock') }}
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Dialog
            v-model:open="lockOpen"
            :title="$t('events.lock.title')"
            :description="$t('events.lock.body')"
            :confirm-label="$t('events.lock.confirm')"
            :cancel-label="$t('events.lock.cancel')"
            confirm-variant="danger"
            :busy="lockBusy"
            @confirm="confirmLock"
        />
    </AppLayout>
</template>
