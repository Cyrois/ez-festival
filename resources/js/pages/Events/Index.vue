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
import { useEventLockActions } from '../../composables/useEventLockActions';

defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

const {
    lockOpen,
    unlockOpen,
    actionBusy,
    openLock,
    openUnlock,
    confirmLock,
    confirmUnlock,
} = useEventLockActions();

const formatDates = (event) => `${event.starts_on} – ${event.ends_on}`;
</script>

<template>
    <AppLayout :title="$t('events.title')">
        <div class="mb-6">
            <h1 class="m-0 text-2xl font-bold tracking-tight">
                {{ $t('events.title') }}
            </h1>
            <p class="mt-1 mb-0 text-sm text-muted">
                {{ $t('events.lead') }}
            </p>
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
                            <div class="flex flex-wrap gap-1.5">
                                <Badge
                                    v-if="event.is_active"
                                    variant="primary"
                                    pill
                                >
                                    {{ $t('events.status.active') }}
                                </Badge>
                                <Badge
                                    v-if="event.is_locked"
                                    variant="warning"
                                    pill
                                >
                                    {{ $t('events.status.locked') }}
                                </Badge>
                                <Badge
                                    v-if="event.is_past"
                                    variant="neutral"
                                    pill
                                >
                                    {{ $t('events.status.past') }}
                                </Badge>
                            </div>
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
                                <Button
                                    v-else
                                    variant="secondary"
                                    size="sm"
                                    @click="openUnlock(event)"
                                >
                                    {{ $t('events.actions.unlock') }}
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
            confirm-variant="secondary"
            :busy="actionBusy"
            @confirm="confirmLock"
        />

        <Dialog
            v-model:open="unlockOpen"
            :title="$t('events.unlock.title')"
            :description="$t('events.unlock.body')"
            :confirm-label="$t('events.unlock.confirm')"
            :cancel-label="$t('events.unlock.cancel')"
            confirm-variant="secondary"
            :busy="actionBusy"
            @confirm="confirmUnlock"
        />
    </AppLayout>
</template>
