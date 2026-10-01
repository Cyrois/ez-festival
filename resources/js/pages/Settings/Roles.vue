<script setup>
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Checkbox } from '../../components/ui/checkbox';
import { DataTable } from '../../components/ui/data-table';
import { Dialog } from '../../components/ui/dialog';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { SegmentedControl } from '../../components/ui/segmented-control';
import { emphasisParts } from '../../lib/emphasisParts';
import { fieldError } from '../../lib/fieldError';
import { navigateDataTableRow } from '../../lib/dataTableRowNavigation';
import { roleColumns } from './roleColumns';
import { roleMatchHint } from './roleMatchHint';
import { ROLE_STATUSES, rolesQuery } from './rolesFilters';
import { Link, router, useForm } from '@inertiajs/vue3';
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
    columnDefs: [{ targets: 3, className: 'text-right' }],
    createdRow: (row, role) => {
        if (props.canManageRoles) {
            navigateDataTableRow(
                row,
                role,
                (item) => `/settings/roles/${item.id}/edit`,
            );
        }
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

// Add dialog. Existing roles use the dedicated edit page.
const formOpen = ref(false);
const form = useForm({ name: '', can_read_team_notes: false });

// Name as last sent to the server, so the "matches" hint never follows later typing.
const submittedName = ref(null);
const matchParts = computed(() => {
    const hint = roleMatchHint({
        match: form.errors.name_match,
        submitted: submittedName.value,
        current: form.name,
    });

    return hint
        ? emphasisParts(trans, 'settings.roles.form.match_hint', hint)
        : [];
});

const focusName = async () => {
    await nextTick();
    document.getElementById('role-name')?.focus();
};

const openAdd = () => {
    form.reset();
    form.can_read_team_notes = false;
    form.clearErrors();
    formOpen.value = true;
    focusName();
};

const closeForm = () => {
    formOpen.value = false;
    submittedName.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    const options = {
        preserveScroll: true,
        onSuccess: async () => {
            closeForm();
            await nextTick();
            table.value?.reload(false);
        },
    };

    form.clearErrors();
    submittedName.value = form.name;

    form.post('/settings/roles', options);
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
                <component
                    :is="canManageRoles ? Link : 'div'"
                    v-for="role in roles.data"
                    :key="`card-${role.id}`"
                    :href="
                        canManageRoles
                            ? `/settings/roles/${role.id}/edit`
                            : undefined
                    "
                    class="rounded-xl border border-line bg-ground p-4 text-charcoal no-underline"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div
                                :class="[
                                    'flex flex-wrap items-center gap-2 font-semibold',
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
                        </div>
                        <Icon
                            v-if="canManageRoles"
                            :name="['fas', 'chevron-right']"
                            class="text-muted"
                            size="sm"
                        />
                    </div>
                    <div
                        class="mt-2 flex items-center gap-2 text-sm text-muted"
                    >
                        <Icon
                            :name="
                                role.can_read_team_notes
                                    ? ['fas', 'check']
                                    : ['fas', 'minus']
                            "
                            size="sm"
                        />
                        {{ $t('settings.roles.columns.team_notes') }}
                    </div>
                </component>
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
                    <template #teamNotesCell="{ rowData }">
                        <Icon
                            :name="
                                rowData.can_read_team_notes
                                    ? ['fas', 'check']
                                    : ['fas', 'minus']
                            "
                            :class="
                                rowData.can_read_team_notes
                                    ? 'text-success'
                                    : 'text-muted'
                            "
                            :aria-label="
                                $t(
                                    rowData.can_read_team_notes
                                        ? 'settings.roles.permissions.enabled'
                                        : 'settings.roles.permissions.disabled',
                                )
                            "
                        />
                    </template>
                    <template #openCell="{ rowData }">
                        <Link
                            v-if="canManageRoles"
                            :href="`/settings/roles/${rowData.id}/edit`"
                            class="inline-flex text-muted hover:text-primary"
                            :aria-label="
                                $t('settings.roles.actions.rename', {
                                    name: rowData.name,
                                })
                            "
                        >
                            <Icon
                                :name="['fas', 'chevron-right']"
                                size="sm"
                            />
                        </Link>
                        <span
                            v-else
                            class="text-muted"
                        >
                            {{ $t('data_table.empty_value') }}
                        </span>
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

        <Dialog
            :open="formOpen"
            :title="$t('settings.roles.form.add_title')"
            :confirm-label="$t('settings.roles.form.submit_add')"
            :cancel-label="$t('settings.roles.form.cancel')"
            confirm-variant="primary"
            cancel-variant="outline-primary"
            :busy="form.processing"
            sectioned
            @update:open="(open) => !open && closeForm()"
            @confirm="submitForm"
        >
            <form
                class="flex flex-col gap-1.5"
                @submit.prevent="submitForm"
            >
                <FormField
                    :label="$t('settings.roles.form.name')"
                    :error="fieldError(form, 'name')"
                    html-for="role-name"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="form.name"
                            type="text"
                            :invalid="invalid"
                            maxlength="255"
                            autocomplete="off"
                        />
                    </template>
                </FormField>
                <p class="m-0 text-xs leading-snug text-muted">
                    {{ $t('settings.roles.form.unique_hint') }}
                    <template v-if="matchParts.length">
                        <template
                            v-for="(part, index) in matchParts"
                            :key="index"
                        >
                            <code
                                v-if="part.emphasis"
                                class="rounded border border-line bg-page px-1 font-mono text-[11px] whitespace-pre text-charcoal"
                                >{{ part.text }}</code
                            >
                            <template v-else>{{ part.text }}</template>
                        </template>
                    </template>
                    <template v-else>
                        {{ $t('settings.roles.form.new_hint') }}
                    </template>
                </p>
                <div class="mt-4 border-t border-line pt-4">
                    <h3 class="m-0 text-sm font-bold text-charcoal">
                        {{ $t('settings.roles.permissions.title') }}
                    </h3>
                    <Checkbox
                        v-model="form.can_read_team_notes"
                        class="mt-3"
                    >
                        <span class="font-semibold">
                            {{
                                $t(
                                    'settings.roles.permissions.can_read_team_notes',
                                )
                            }}
                        </span>
                    </Checkbox>
                    <p class="mt-1 mb-0 pl-6 text-xs leading-snug text-muted">
                        {{
                            $t(
                                'settings.roles.permissions.can_read_team_notes_help',
                            )
                        }}
                    </p>
                </div>
            </form>
        </Dialog>
    </SettingsLayout>
</template>
