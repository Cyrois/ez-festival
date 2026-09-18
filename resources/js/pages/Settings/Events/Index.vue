<script setup>
import SettingsLayout from '../../../layouts/SettingsLayout.vue';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { EmptyState } from '../../../components/ui/empty-state';
import { Icon } from '../../../components/ui/icon';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../../../components/ui/table';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

const setPrimaryBusy = ref(false);

const setPrimary = (event) => {
    if (event.is_active || setPrimaryBusy.value) {
        return;
    }
    setPrimaryBusy.value = true;
    router.post(
        `/settings/events/${event.id}/set-primary`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                setPrimaryBusy.value = false;
            },
        },
    );
};

const breadcrumbs = computed(() => [
    {
        label: trans('app.name'),
        href: '/dashboard',
    },
    {
        label: trans('nav.settings'),
        href: '/settings/events',
    },
    {
        label: trans('settings.events.title'),
    },
]);

const formatSubtext = (event) => {
    return `${event.starts_on} – ${event.ends_on}`;
};

const primaryStatus = (event) => {
    if (event.is_locked) {
        return 'locked';
    }
    if (event.is_active) {
        return 'active';
    }
    if (event.is_past) {
        return 'past';
    }
    return null;
};

const statusVariant = {
    active: 'success',
    locked: 'warning',
    past: 'neutral',
};
</script>

<template>
    <SettingsLayout
        :title="$t('settings.events.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div
            class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('settings.events.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('settings.events.lead') }}
                </p>
            </div>
            <Button
                href="/settings/events/create"
                variant="primary"
                class="min-h-11 w-full sm:w-auto"
            >
                <Icon
                    :name="['fas', 'plus']"
                    size="sm"
                />
                {{ $t('settings.events.actions.create') }}
            </Button>
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

        <template v-else>
            <!-- Phone: card stack -->
            <div class="flex flex-col gap-3 md:hidden">
                <div
                    v-for="event in events"
                    :key="`card-${event.id}`"
                    class="rounded-xl border border-line bg-ground p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-semibold">
                                {{ event.name }}
                            </div>
                            <div class="mt-0.5 text-xs text-muted">
                                {{ formatSubtext(event) }}
                            </div>
                        </div>
                        <Badge
                            v-if="primaryStatus(event)"
                            :variant="statusVariant[primaryStatus(event)]"
                            pill
                        >
                            {{ $t(`events.status.${primaryStatus(event)}`) }}
                        </Badge>
                    </div>
                    <div class="mt-4 flex flex-wrap justify-end gap-2">
                        <Button
                            v-if="!event.is_active"
                            variant="outline"
                            size="sm"
                            class="min-h-11"
                            :loading="setPrimaryBusy"
                            :disabled="setPrimaryBusy"
                            @click="setPrimary(event)"
                        >
                            <Icon
                                :name="['fas', 'star']"
                                size="sm"
                                class="mr-1.5"
                            />
                            {{ $t('settings.events.actions.set_primary') }}
                        </Button>
                        <Button
                            v-if="!event.is_locked"
                            :href="`/settings/events/${event.id}/edit`"
                            variant="primary"
                            size="sm"
                            class="min-h-11"
                        >
                            <Icon
                                :name="['fas', 'pencil']"
                                size="sm"
                                class="mr-1.5"
                            />
                            {{ $t('settings.events.actions.edit') }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- md+: table -->
            <div
                class="hidden overflow-hidden rounded-xl border border-line bg-ground md:block"
            >
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {{ $t('settings.events.columns.event') }}
                                </TableHead>
                                <TableHead>
                                    {{ $t('settings.events.columns.status') }}
                                </TableHead>
                                <TableHead class="text-right">
                                    <span class="sr-only">{{
                                        $t('settings.events.columns.actions')
                                    }}</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="event in events"
                                :key="event.id"
                            >
                                <TableCell>
                                    <div class="font-semibold">
                                        {{ event.name }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-muted">
                                        {{ formatSubtext(event) }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        v-if="primaryStatus(event)"
                                        :variant="
                                            statusVariant[primaryStatus(event)]
                                        "
                                        pill
                                    >
                                        {{
                                            $t(
                                                `events.status.${primaryStatus(event)}`,
                                            )
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div
                                        class="flex flex-wrap justify-end gap-2"
                                    >
                                        <Button
                                            v-if="!event.is_active"
                                            variant="outline"
                                            size="sm"
                                            :loading="setPrimaryBusy"
                                            :disabled="setPrimaryBusy"
                                            @click="setPrimary(event)"
                                        >
                                            <Icon
                                                :name="['fas', 'star']"
                                                size="sm"
                                                class="mr-1.5"
                                            />
                                            {{
                                                $t(
                                                    'settings.events.actions.set_primary',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="!event.is_locked"
                                            :href="`/settings/events/${event.id}/edit`"
                                            variant="primary"
                                            size="sm"
                                        >
                                            <Icon
                                                :name="['fas', 'pencil']"
                                                size="sm"
                                                class="mr-1.5"
                                            />
                                            {{
                                                $t(
                                                    'settings.events.actions.edit',
                                                )
                                            }}
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </template>
    </SettingsLayout>
</template>
