<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { CardTitle } from '../../components/ui/card';
import { DataTable } from '../../components/ui/data-table';
import { Dialog } from '../../components/ui/dialog';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { useFlashToast } from '../../composables/useFlashToast';
import { toastFormErrors } from '../../lib/fieldError';
import { activateDataTableRow } from '../../lib/dataTableRowNavigation';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    canEdit: { type: Boolean, required: true },
});
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.meals'), href: '/meals' },
    { label: trans('meals.settings.title') },
]);
const canWrite = computed(() => props.canEdit && !props.event.is_locked);
const disabledReason = computed(() =>
    props.event.is_locked
        ? trans('events.read_only_locked')
        : !props.canEdit
          ? trans('meals.types.read_only')
          : '',
);
const table = ref(null);
const search = ref('');
let searchTimer;
watch(search, (value) => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => table.value?.search(value), 300);
});
onUnmounted(() => window.clearTimeout(searchTimer));
const dialogOpen = ref(false);
const editingId = ref(null);
const form = useForm({ name: '', starts_at: '', ends_at: '' });
const { showError, showFormError } = useFlashToast();

const columns = computed(() => [
    {
        data: 'name',
        title: trans('meals.types.name'),
        render: { display: '#nameCell' },
    },
    {
        data: 'starts_at',
        title: trans('meals.types.window'),
        render: { display: '#windowCell' },
        searchable: false,
    },
    {
        data: null,
        defaultContent: '',
        title: '',
        orderable: false,
        searchable: false,
        render: { display: '#actionsCell' },
    },
]);
const tableOptions = computed(() => ({
    serverSide: true,
    lengthChange: false,
    pageLength: 25,
    order: [],
    layout: { topStart: null, topEnd: null },
    columnDefs: [{ targets: 2, className: 'text-right' }],
    createdRow: (row, type) => {
        if (!canWrite.value) {
            row.title = disabledReason.value;
            return;
        }

        activateDataTableRow(row, type, (item) => openForm(item));
    },
    language: { emptyTable: trans('meals.types.empty') },
}));

const openForm = (type = null) => {
    if (!canWrite.value) return;
    editingId.value = type?.id ?? null;
    form.reset();
    form.clearErrors();
    form.name = type?.name ?? '';
    form.starts_at = type?.starts_at ?? '';
    form.ends_at = type?.ends_at ?? '';
    dialogOpen.value = true;
};
const submit = () => {
    if (!canWrite.value || form.processing) return;
    const options = {
        preserveScroll: true,
        onSuccess: async () => {
            dialogOpen.value = false;
            form.reset();
            await nextTick();
            table.value?.reload(editingId.value === null);
        },
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    };
    const url = `/events/${props.event.id}/meals/types`;
    if (editingId.value === null) form.post(url, options);
    else form.put(`${url}/${editingId.value}`, options);
};
</script>

<template>
    <AppLayout
        :title="$t('meals.settings.title')"
        :breadcrumbs="breadcrumbs"
        back-href="/meals"
        :back-label="$t('meals.back')"
    >
        <div class="container mx-auto flex flex-col gap-5">
            <header>
                <h1 class="m-0 text-2xl font-bold text-charcoal">
                    {{ $t('meals.settings.title') }}
                </h1>
                <p class="mt-1 text-sm text-muted">
                    {{ $t('meals.settings.event', { event: event.name }) }}
                </p>
            </header>
            <p
                v-if="event.is_locked"
                class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                {{ $t('events.read_only_locked') }}
            </p>
            <section>
                <div
                    class="mb-4 flex flex-wrap items-start justify-between gap-4"
                >
                    <div>
                        <CardTitle>{{ $t('meals.types.title') }}</CardTitle>
                        <p class="mt-1 text-sm text-muted">
                            {{ $t('meals.types.hint') }}
                        </p>
                    </div>
                    <span
                        :title="disabledReason || undefined"
                        :tabindex="!canWrite ? 0 : undefined"
                        :aria-label="!canWrite ? disabledReason : undefined"
                    >
                        <Button
                            :disabled="!canWrite"
                            @click="openForm()"
                        >
                            <Icon
                                :name="['fas', 'plus']"
                                size="sm"
                            />
                            {{ $t('meals.types.add') }}
                        </Button>
                    </span>
                </div>
                <form
                    class="relative mb-4 w-full sm:w-64"
                    role="search"
                    @submit.prevent
                >
                    <Icon
                        :name="['fas', 'magnifying-glass']"
                        size="sm"
                        class="pointer-events-none absolute top-3.5 left-3 z-10 text-muted"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        :placeholder="$t('meals.types.search')"
                        :aria-label="$t('meals.types.search')"
                        class="min-h-11 pl-9"
                    />
                </form>
                <DataTable
                    :key="event.id"
                    ref="table"
                    ajax="/meals/settings/meal-types"
                    :columns="columns"
                    :options="tableOptions"
                >
                    <template #nameCell="{ rowData }">
                        <span class="font-semibold">{{ rowData.name }}</span>
                    </template>
                    <template #windowCell="{ rowData }">
                        <span class="whitespace-nowrap">
                            {{ rowData.starts_at }}–{{ rowData.ends_at }}
                        </span>
                    </template>
                    <template #actionsCell="{ rowData }">
                        <span
                            :title="disabledReason || undefined"
                            :tabindex="!canWrite ? 0 : undefined"
                            :aria-label="!canWrite ? disabledReason : undefined"
                        >
                            <button
                                type="button"
                                class="inline-flex text-muted hover:text-primary disabled:cursor-not-allowed disabled:opacity-45"
                                :disabled="!canWrite"
                                :aria-label="
                                    $t('meals.types.edit_name', {
                                        name: rowData.name,
                                    })
                                "
                                @click="openForm(rowData)"
                            >
                                <Icon
                                    :name="['fas', 'chevron-right']"
                                    size="sm"
                                />
                            </button>
                        </span>
                    </template>
                </DataTable>
            </section>
        </div>
        <Dialog
            :open="dialogOpen"
            :title="
                editingId === null
                    ? $t('meals.types.add')
                    : $t('meals.types.edit')
            "
            :confirm-label="$t('actions.save')"
            confirm-variant="primary"
            :busy="form.processing"
            :confirm-disabled="!canWrite"
            role="dialog"
            sectioned
            focus-trap
            @update:open="dialogOpen = $event"
            @confirm="submit"
        >
            <form
                class="space-y-4"
                @submit.prevent="submit"
            >
                <FormField
                    v-slot="{ id, invalid }"
                    :label="$t('meals.types.name')"
                    :error="form.errors.name"
                    required
                >
                    <Input
                        :id="id"
                        v-model="form.name"
                        maxlength="50"
                        :invalid="invalid"
                        :disabled="form.processing || !canWrite"
                        autocomplete="off"
                        required
                    />
                </FormField>
                <div class="grid grid-cols-2 gap-4">
                    <FormField
                        v-slot="{ id, invalid }"
                        :label="$t('meals.types.start')"
                        :error="form.errors.starts_at"
                        required
                    >
                        <Input
                            :id="id"
                            v-model="form.starts_at"
                            type="time"
                            step="60"
                            :invalid="invalid"
                            :disabled="form.processing || !canWrite"
                            required
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        :label="$t('meals.types.end')"
                        :error="form.errors.ends_at"
                        required
                    >
                        <Input
                            :id="id"
                            v-model="form.ends_at"
                            type="time"
                            step="60"
                            :invalid="invalid"
                            :disabled="form.processing || !canWrite"
                            required
                        />
                    </FormField>
                </div>
                <p class="text-xs text-muted">
                    {{ $t('meals.types.overnight_hint') }}
                </p>
            </form>
        </Dialog>
    </AppLayout>
</template>
