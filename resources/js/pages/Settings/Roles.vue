<script setup>
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { DataTable } from '../../components/ui/data-table';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Dialog } from '../../components/ui/dialog';
import { useFlashToast } from '../../composables/useFlashToast';
import { roleColumns } from './roleColumns';
import { rolesQuery } from './rolesFilters';
import { Link, router } from '@inertiajs/vue3';
import { navigateDataTableRow } from '../../lib/dataTableRowNavigation';
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

const peopleLabel = (count) =>
    transChoice('settings.roles.people_count', count, { count });
const columns = computed(() => roleColumns(trans));
const table = ref(null);
const dataTableKey = computed(() => props.filters.search);
const dataTableUrl = computed(() => {
    const query = new URLSearchParams();

    if (props.filters.search) {
        query.set('query', props.filters.search);
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

        navigateDataTableRow(
            row,
            role,
            (item) => `/settings/roles/${item.id}/edit`,
        );
    },
    language: {
        emptyTable: trans('settings.roles.empty'),
        zeroRecords: trans('settings.roles.empty_filtered'),
    },
}));

// Search runs on the server.
const search = ref(props.filters.search);
let searchTimer;

const applyFilters = () => {
    window.clearTimeout(searchTimer);
    router.get('/settings/roles', rolesQuery({ search: search.value }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
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
    },
);

onUnmounted(() => window.clearTimeout(searchTimer));

const openAdd = () => router.visit('/settings/roles/create');

const { showFormError } = useFlashToast();
const deleteRole = ref(null);
const deleteBusy = ref(false);
const deleteOpen = computed({
    get: () => deleteRole.value !== null,
    set: (open) => {
        if (!open) deleteRole.value = null;
    },
});

const confirmDelete = () => {
    if (!deleteRole.value || deleteBusy.value) return;
    deleteBusy.value = true;
    router.delete(`/settings/roles/${deleteRole.value.id}`, {
        preserveScroll: true,
        onSuccess: async () => {
            deleteRole.value = null;
            await nextTick();
            table.value?.reload();
        },
        onError: showFormError,
        onFinish: () => {
            deleteBusy.value = false;
        },
    });
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
            </div>

            <div>
                <DataTable
                    :key="dataTableKey"
                    ref="table"
                    :ajax="dataTableUrl"
                    :columns="columns"
                    :options="dataTableOptions"
                >
                    <template #roleCell="{ rowData }">
                        <component
                            :is="canManageRoles ? Link : 'span'"
                            :href="
                                canManageRoles
                                    ? `/settings/roles/${rowData.id}/edit`
                                    : undefined
                            "
                            :class="[
                                'inline-flex items-center gap-2 font-semibold text-charcoal no-underline',
                                canManageRoles &&
                                    'hover:text-primary hover:underline',
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
                        </component>
                    </template>
                    <template #peopleCell="{ rowData }">
                        <span :class="!rowData.active && 'text-muted'">
                            {{ peopleLabel(rowData.people_count) }}
                        </span>
                    </template>
                    <template #openCell="{ rowData }">
                        <div
                            v-if="canManageRoles"
                            class="flex items-center justify-end gap-2"
                        >
                            <span
                                :title="
                                    rowData.people_count > 0
                                        ? $t('settings.roles.delete.in_use')
                                        : undefined
                                "
                                :tabindex="
                                    rowData.people_count > 0 ? 0 : undefined
                                "
                                @click.stop
                            >
                                <Button
                                    type="button"
                                    variant="danger"
                                    size="sm"
                                    :disabled="
                                        rowData.people_count > 0 || deleteBusy
                                    "
                                    @click="deleteRole = rowData"
                                >
                                    {{ $t('settings.roles.delete.action') }}
                                </Button>
                            </span>
                            <Link
                                :href="`/settings/roles/${rowData.id}/edit`"
                                class="inline-flex text-muted hover:text-primary"
                                :aria-label="
                                    $t('data_table.open', {
                                        name: rowData.name,
                                    })
                                "
                            >
                                <Icon
                                    :name="['fas', 'chevron-right']"
                                    size="sm"
                                />
                            </Link>
                        </div>
                    </template>
                </DataTable>
            </div>

            <div class="flex flex-col gap-1.5">
                <p class="m-0 text-xs text-muted/80">
                    {{ $t('settings.roles.admins_note') }}
                </p>
            </div>
        </div>
        <Dialog
            v-model:open="deleteOpen"
            focus-trap
            :title="$t('settings.roles.delete.title')"
            :description="deleteRole?.name"
            :confirm-label="$t('settings.roles.delete.action')"
            :cancel-label="$t('ui.dialog.cancel')"
            :busy="deleteBusy"
            @confirm="confirmDelete"
        />
    </SettingsLayout>
</template>
