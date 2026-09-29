<script setup>
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { DataTable } from '../../components/ui/data-table';
import { Dialog } from '../../components/ui/dialog';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { IconButton } from '../../components/ui/icon-button';
import { Input } from '../../components/ui/input';
import { SegmentedControl } from '../../components/ui/segmented-control';
import { emphasisParts } from '../../lib/emphasisParts';
import { fieldError } from '../../lib/fieldError';
import { roleColumns } from './roleColumns';
import { roleMatchHint } from './roleMatchHint';
import { ROLE_STATUSES, rolesQuery } from './rolesFilters';
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { trans, transChoice } from 'laravel-vue-i18n';

const props = defineProps({
    roles: { type: Object, required: true },
    filters: { type: Object, required: true },
    hasAnyRoles: { type: Boolean, required: true },
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
        selectedClass: 'rounded-none bg-primary-soft py-2 text-primary',
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

// Add / rename dialog.
const editing = ref(null);
const formOpen = ref(false);
const form = useForm({ name: '' });

const isRename = computed(() => editing.value !== null);
const formTitle = computed(() =>
    isRename.value
        ? trans('settings.roles.form.rename_title', {
              name: editing.value.name,
          })
        : trans('settings.roles.form.add_title'),
);
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
    editing.value = null;
    form.reset();
    form.clearErrors();
    formOpen.value = true;
    focusName();
};

const openRename = (role) => {
    editing.value = role;
    form.name = role.name;
    form.clearErrors();
    formOpen.value = true;
    focusName();
};

const closeForm = () => {
    formOpen.value = false;
    editing.value = null;
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

    if (isRename.value) {
        form.put(`/settings/roles/${editing.value.id}`, options);
        return;
    }

    form.post('/settings/roles', options);
};

// Turn off (asks first) / turn on.
const turningOff = ref(null);
const statusBusy = ref(false);

const turnOffParts = computed(() =>
    turningOff.value
        ? emphasisParts(trans, 'settings.roles.turn_off.body', {
              name: turningOff.value.name,
              people: peopleLabel(turningOff.value.people_count),
          })
        : [],
);

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

const confirmTurnOff = () => {
    if (!turningOff.value) {
        return;
    }
    setActive(turningOff.value, false, () => {
        turningOff.value = null;
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
                    class="divide-x divide-line overflow-hidden border border-line bg-ground p-0"
                    unselected-class="rounded-none bg-transparent py-2 text-muted hover:text-charcoal"
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
                    <div class="mt-4 flex flex-wrap justify-end gap-2">
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
                            v-if="role.active"
                            type="button"
                            variant="outline-secondary"
                            size="sm"
                            class="min-h-11"
                            :disabled="statusBusy"
                            @click="turningOff = role"
                        >
                            <Icon
                                :name="['fas', 'power-off']"
                                size="sm"
                            />
                            {{ $t('settings.roles.actions.turn_off') }}
                        </Button>
                        <Button
                            v-else
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
                        <div class="flex items-center justify-end gap-2">
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
                                v-if="rowData.active"
                                type="button"
                                variant="outline-secondary"
                                size="sm"
                                :disabled="statusBusy"
                                @click="turningOff = rowData"
                            >
                                <Icon
                                    :name="['fas', 'power-off']"
                                    size="sm"
                                />
                                {{ $t('settings.roles.actions.turn_off') }}
                            </Button>
                            <Button
                                v-else
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

            <p
                class="m-0 flex items-center gap-2 rounded-lg border border-dashed border-line bg-page px-3 py-2.5 text-xs text-muted"
            >
                <Icon
                    :name="['fas', 'lock']"
                    size="sm"
                />
                <span>
                    <strong class="font-bold">
                        {{ $t('settings.roles.permissions.coming_later') }}
                    </strong>
                    {{ $t('settings.roles.permissions.none_yet') }}
                </span>
            </p>
        </div>

        <Dialog
            :open="formOpen"
            :title="formTitle"
            :confirm-label="
                isRename
                    ? $t('settings.roles.form.save')
                    : $t('settings.roles.form.submit_add')
            "
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
                    <template v-else-if="!isRename">
                        {{ $t('settings.roles.form.new_hint') }}
                    </template>
                </p>
                <p
                    v-if="isRename"
                    class="mt-2 mb-0 text-xs leading-snug text-muted"
                >
                    {{
                        $t('settings.roles.form.rename_keeps', {
                            people: peopleLabel(editing.people_count),
                        })
                    }}
                </p>
            </form>
        </Dialog>

        <Dialog
            :open="turningOff !== null"
            :title="$t('settings.roles.turn_off.title')"
            :confirm-label="$t('settings.roles.turn_off.confirm')"
            :cancel-label="$t('settings.roles.form.cancel')"
            confirm-variant="danger"
            cancel-variant="outline-primary"
            :confirm-icon="['fas', 'power-off']"
            :busy="statusBusy"
            sectioned
            @update:open="(open) => !open && (turningOff = null)"
            @confirm="confirmTurnOff"
        >
            <template #description>
                <template
                    v-for="(part, index) in turnOffParts"
                    :key="index"
                >
                    <strong
                        v-if="part.emphasis"
                        class="font-bold"
                        >{{ part.text }}</strong
                    >
                    <template v-else>{{ part.text }}</template>
                </template>
            </template>
        </Dialog>
    </SettingsLayout>
</template>
