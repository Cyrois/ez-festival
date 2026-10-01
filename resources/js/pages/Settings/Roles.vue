<script setup>
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { DataTable } from '../../components/ui/data-table';
import { Icon } from '../../components/ui/icon';
import { IconButton } from '../../components/ui/icon-button';
import { Input } from '../../components/ui/input';
import { SegmentedControl } from '../../components/ui/segmented-control';
import { roleColumns } from './roleColumns';
import { ROLE_STATUSES, rolesQuery } from './rolesFilters';
import { router } from '@inertiajs/vue3';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { trans, transChoice } from 'laravel-vue-i18n';

const props = defineProps({
    roles: { type: Object, required: true },
    filters: { type: Object, required: true },
    hasAnyRoles: { type: Boolean, required: true },
    canManageRoles: { type: Boolean, required: true },
});

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.settings'), href: '/settings/events' },
    { label: trans('settings.roles.title') },
]);

const statusOptions = computed(() =>
    ROLE_STATUSES.map((value) => ({
        value,
        label: trans(`settings.roles.filter.${value}`),
    })),
);

const peopleLabel = (count) =>
    transChoice('settings.roles.people_count', count, { count });
const columns = computed(() => roleColumns(trans));
const table = ref(null);
const dataTableKey = computed(
    () => `${props.filters.search}:${props.filters.status}`,
);
const dataTableUrl = computed(() => {
    const query = new URLSearchParams();

    if (props.filters.search) {
        query.set('query', props.filters.search);
    }
    if (props.filters.status !== 'on') {
        query.set('status', props.filters.status);
    }

    const suffix = query.toString();

    return `/settings/roles/data${suffix ? `?${suffix}` : ''}`;
});
const dataTableOptions = computed(() => ({
    serverSide: true,
    searching: false,
    lengthChange: false,
    pageLength: 25,
    order: [[0, 'asc']],
    layout: {
        topStart: null,
        topEnd: null,
    },
    columnDefs: [{ targets: 2, className: 'text-right' }],
    createdRow: (row, role) => {
        if (!props.canManageRoles) {
            return;
        }

        row.classList.add('cursor-pointer');
        row.dataset.rowLink = '';
        row.addEventListener('click', (event) => {
            if (
                event.target.closest('a, button, input, select, textarea') ||
                window.getSelection()?.toString()
            ) {
                return;
            }

            openRename(role);
        });
    },
    language: {
        emptyTable: trans('settings.roles.empty'),
        zeroRecords: trans('settings.roles.empty_filtered'),
    },
}));

// Filters: search and On | Off | All run on the server.
const search = ref(props.filters.search);
const status = ref(props.filters.status);
let searchTimer;

const applyFilters = () => {
    window.clearTimeout(searchTimer);
    router.get(
        '/settings/roles',
        rolesQuery({ search: search.value, status: status.value }),
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const updateStatus = (value) => {
    status.value = value;
    applyFilters();
};

watch(search, (value) => {
    if (value === props.filters.search) {
        return;
    }
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(applyFilters, 300);
});

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        status.value = filters.status;
    },
);

onUnmounted(() => window.clearTimeout(searchTimer));

const openAdd = () => router.visit('/settings/roles/create');
const openRename = (role) => router.visit(`/settings/roles/${role.id}/edit`);

// Turn on inactive roles.
const statusBusy = ref(false);

const setActive = (role, active, onSuccess = () => {}) => {
    statusBusy.value = true;
    router.put(
        `/settings/roles/${role.id}/status`,
        { active },
        {
            preserveScroll: true,
            onSuccess: async () => {
                onSuccess();
                await nextTick();
                table.value?.reload();
            },
            onFinish: () => {
                statusBusy.value = false;
            },
        },
    );
};
</script>

<template>
    <SettingsLayout
        :title="$t('settings.roles.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto flex flex-col gap-4">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-xl">
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ $t('settings.roles.title') }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('settings.roles.lead') }}
                    </p>
                </div>
                <Button
                    v-if="canManageRoles"
                    type="button"
                    class="min-h-11 w-full sm:min-h-10 sm:w-auto"
                    @click="openAdd"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                    />
                    {{ $t('settings.roles.add') }}
                </Button>
            </header>

            <div class="flex flex-wrap items-center gap-2">
                <form
                    class="relative w-full sm:w-80"
                    role="search"
                    @submit.prevent="applyFilters"
                >
                    <Icon
                        :name="['fas', 'magnifying-glass']"
                        class="pointer-events-none absolute top-3 left-3 z-10 text-muted"
                        size="sm"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        class="pl-9"
                        :aria-label="$t('settings.roles.search')"
                        :placeholder="$t('settings.roles.search')"
                        maxlength="255"
                    />
                </form>
                <SegmentedControl
                    :model-value="status"
                    :options="statusOptions"
                    :aria-label="$t('settings.roles.filter.label')"
                    variant="joined"
                    @update:model-value="updateStatus"
                />
            </div>

            <!-- Phone: card stack -->
            <div class="flex flex-col gap-3 md:hidden">
                <div
                    v-for="role in roles.data"
                    :key="`card-${role.id}`"
                    class="rounded-xl border border-line bg-ground p-4"
                >
                    <div
                        :class="[
                            'flex flex-wrap items-center gap-2 font-bold',
                            !role.active && 'text-muted',
                        ]"
                    >
                        {{ role.name }}
                        <Badge
                            v-if="!role.active"
                            pill
                            class="font-bold text-muted"
                        >
                            {{ $t('settings.roles.status.off') }}
                        </Badge>
                    </div>
                    <div class="mt-0.5 text-sm text-muted">
                        {{ peopleLabel(role.people_count) }}
                    </div>

                    <div
                        v-if="canManageRoles"
                        class="mt-4 flex flex-wrap justify-end gap-2"
                    >
                        <IconButton
                            :icon="['fas', 'pencil']"
                            :label="
                                $t('settings.roles.actions.rename', {
                                    name: role.name,
                                })
                            "
                            tone="edit"
                            class="h-11 w-11"
                            @click="openRename(role)"
                        />
                        <Button
                            v-if="!role.active"
                            type="button"
                            variant="outline-primary"
                            size="sm"
                            class="min-h-11"
                            :disabled="statusBusy"
                            @click="setActive(role, true)"
                        >
                            <Icon
                                :name="['fas', 'power-off']"
                                size="sm"
                            />
                            {{ $t('settings.roles.actions.turn_on') }}
                        </Button>
                    </div>
                </div>
                <p
                    v-if="roles.data.length === 0"
                    class="m-0 rounded-xl border border-line bg-ground px-4 py-6 text-center text-sm text-muted"
                >
                    {{
                        hasAnyRoles
                            ? $t('settings.roles.empty_filtered')
                            : $t('settings.roles.empty')
                    }}
                </p>
            </div>

            <!-- md+: table -->
            <div class="hidden md:block">
                <DataTable
                    :key="dataTableKey"
                    ref="table"
                    :ajax="dataTableUrl"
                    :columns="columns"
                    :options="dataTableOptions"
                >
                    <template #roleCell="{ rowData }">
                        <span
                            :class="[
                                'inline-flex items-center gap-2 font-bold',
                                !rowData.active && 'text-muted',
                            ]"
                        >
                            {{ rowData.name }}
                            <Badge
                                v-if="!rowData.active"
                                pill
                                class="font-bold text-muted"
                            >
                                {{ $t('settings.roles.status.off') }}
                            </Badge>
                        </span>
                    </template>
                    <template #peopleCell="{ rowData }">
                        <span :class="!rowData.active && 'text-muted'">
                            {{ peopleLabel(rowData.people_count) }}
                        </span>
                    </template>
                    <template #actionsCell="{ rowData }">
                        <div
                            v-if="canManageRoles"
                            class="flex items-center justify-end gap-2"
                        >
                            <IconButton
                                :icon="['fas', 'pencil']"
                                :label="
                                    $t('settings.roles.actions.rename', {
                                        name: rowData.name,
                                    })
                                "
                                tone="edit"
                                @click="openRename(rowData)"
                            />
                            <Button
                                v-if="!rowData.active"
                                type="button"
                                variant="outline-primary"
                                size="sm"
                                :disabled="statusBusy"
                                @click="setActive(rowData, true)"
                            >
                                <Icon
                                    :name="['fas', 'power-off']"
                                    size="sm"
                                />
                                {{ $t('settings.roles.actions.turn_on') }}
                            </Button>
                        </div>
                    </template>
                </DataTable>
            </div>

            <div
                v-if="roles.meta?.last_page > 1"
                class="flex flex-wrap items-center justify-between gap-3 md:hidden"
            >
                <p class="m-0 text-sm text-muted">
                    {{
                        $t('settings.roles.pagination', {
                            from: roles.meta.from,
                            to: roles.meta.to,
                            total: roles.meta.total,
                        })
                    }}
                </p>
                <nav
                    class="flex gap-2"
                    :aria-label="$t('settings.roles.pagination_label')"
                >
                    <Button
                        :href="roles.links.prev || ''"
                        :disabled="!roles.links.prev"
                        variant="outline"
                    >
                        {{ $t('settings.roles.previous') }}
                    </Button>
                    <Button
                        :href="roles.links.next || ''"
                        :disabled="!roles.links.next"
                        variant="outline"
                    >
                        {{ $t('settings.roles.next') }}
                    </Button>
                </nav>
            </div>

            <div class="flex flex-col gap-1.5">
                <p class="m-0 text-xs text-muted">
                    {{ $t('settings.roles.off_note') }}
                </p>
                <p class="m-0 text-xs text-muted/80">
                    {{ $t('settings.roles.admins_note') }}
                </p>
            </div>
        </div>
    </SettingsLayout>
</template>
