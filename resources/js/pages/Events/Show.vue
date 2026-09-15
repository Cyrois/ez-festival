<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Dialog } from '../../components/ui/dialog';
import { useEventLockActions } from '../../composables/useEventLockActions';
import { computed, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
});

const {
    lockOpen,
    unlockOpen,
    actionBusy,
    targetEvent,
    openLock,
    openUnlock,
    confirmLock,
    confirmUnlock,
} = useEventLockActions();

watch(
    () => props.event,
    (event) => {
        targetEvent.value = event;
    },
    { immediate: true },
);

const breadcrumbs = computed(() => [
    {
        label: trans('events.title'),
        href: '/events',
    },
    {
        label: props.event.name,
    },
]);

const readOnlyMessage = computed(() => {
    if (props.event.is_locked) {
        return trans('events.read_only_locked');
    }

    if (!props.event.is_active) {
        return trans('events.read_only_inactive');
    }

    return '';
});
</script>

<template>
    <AppLayout
        :title="event.name"
        :breadcrumbs="breadcrumbs"
    >
        <div
            v-if="event.is_read_only"
            class="mb-4 rounded-lg border border-warning/20 bg-warning/10 px-4 py-3 text-sm font-semibold text-warning"
        >
            {{ readOnlyMessage }}
        </div>

        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ event.name }}
                    </h1>
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
                <Button
                    href="/events"
                    variant="ghost"
                    size="sm"
                    class="px-0"
                >
                    {{ $t('events.back_to_list') }}
                </Button>
            </div>
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

        <Card class="space-y-4 p-5">
            <div>
                <p
                    class="m-0 text-xs font-bold tracking-wide text-muted uppercase"
                >
                    {{ $t('events.fields.name') }}
                </p>
                <p class="m-0 mt-1 text-sm font-semibold">
                    {{ event.name }}
                </p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p
                        class="m-0 text-xs font-bold tracking-wide text-muted uppercase"
                    >
                        {{ $t('events.fields.starts_on') }}
                    </p>
                    <p class="m-0 mt-1 text-sm font-semibold">
                        {{ event.starts_on }}
                    </p>
                </div>
                <div>
                    <p
                        class="m-0 text-xs font-bold tracking-wide text-muted uppercase"
                    >
                        {{ $t('events.fields.ends_on') }}
                    </p>
                    <p class="m-0 mt-1 text-sm font-semibold">
                        {{ event.ends_on }}
                    </p>
                </div>
            </div>
            <div>
                <p
                    class="m-0 text-xs font-bold tracking-wide text-muted uppercase"
                >
                    {{ $t('events.fields.timezone') }}
                </p>
                <p class="m-0 mt-1 text-sm font-semibold">
                    {{ event.timezone }}
                </p>
            </div>
        </Card>

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
