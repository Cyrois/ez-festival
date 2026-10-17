<script setup>
import { useReturnNavigation } from '../../composables/useReturnNavigation';
import { useLandingState } from '../../composables/useLandingState';
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { DataTable } from '../../components/ui/data-table';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { LabelCombobox } from '../../components/ui/label-combobox';
import { Popup } from '../../components/ui/popup';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { useFlashToast } from '../../composables/useFlashToast';
import { toastFormErrors } from '../../lib/fieldError';
import { credentialsHome } from '../../lib/credentialsNavigation';
import { Tag } from '../../components/ui/tag';
import { navigateDataTableRow } from '../../lib/dataTableRowNavigation';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { entitlementColumns } from './entitlementColumns';

const { returnUrl } = useReturnNavigation();

const props = defineProps({
    items: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
    labelColors: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    canWrite: { type: Boolean, default: false },
    canEditItems: { type: Boolean, default: false },
    canAdjustStock: { type: Boolean, default: false },
});

const page = usePage();
const event = computed(() => page.props.activeEvent ?? {});
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    {
        label: trans('nav.credentials'),
        href: credentialsHome(page.props.permissions),
    },
    { label: trans('nav.credentials.entitlements') },
]);
const addOpen = ref(false);
const { showError, showFormError } = useFlashToast();
const locationItems = computed(() =>
    props.locations.map((location) => ({
        value: location.id,
        title: location.name,
    })),
);
const selectedLabelIds = ref([]);
const search = ref('');
const table = ref(null);
const createForm = useForm({
    name: '',
    opening_balance: 0,
    location_id: '',
    label_ids: [],
    new_labels: [],
});
const createLabelError = computed(
    () =>
        createForm.errors.label_ids ||
        Object.entries(createForm.errors).find(([key]) =>
            key.startsWith('new_labels.'),
        )?.[1] ||
        '',
);
const columns = computed(() => entitlementColumns(trans));
const landingState = useLandingState(
    'entitlements',
    { search, selectedLabelIds },
    () =>
        table.value?.filterRows(
            'entitlementFilters',
            hasFilters.value ? matchesFilters : null,
            false,
        ),
);
const options = computed(() => ({
    ...landingState,
    lengthChange: false,
    pageLength: 25,
    order: [[0, 'asc']],
    layout: {
        topStart: null,
        topEnd: null,
    },
    columnDefs: [
        { targets: 2, className: 'text-right font-semibold tabular-nums' },
        { targets: 3, className: 'text-right' },
    ],
    createdRow: (row, item) =>
        navigateDataTableRow(row, item, (record) =>
            returnUrl(`/credentials/entitlements/${record.id}`),
        ),
    language: {
        emptyTable: trans('credentials.entitlements.items.empty'),
        zeroRecords: trans('credentials.entitlements.items.empty_filtered'),
    },
}));
const matchesFilters = (item) => {
    const query = search.value.trim().toLocaleLowerCase();
    const matchesText =
        query === '' ||
        [item.name, ...item.labels.map((label) => label.name)]
            .join(' ')
            .toLocaleLowerCase()
            .includes(query);
    const matchesLabels = selectedLabelIds.value.every((labelId) =>
        item.labels.some((label) => label.id === labelId),
    );

    return matchesText && matchesLabels;
};
const hasFilters = computed(
    () => search.value.trim() !== '' || selectedLabelIds.value.length > 0,
);
const clearFilters = () => {
    search.value = '';
    selectedLabelIds.value = [];
};
watch([search, selectedLabelIds], () => {
    table.value?.filterRows(
        'entitlementFilters',
        hasFilters.value ? matchesFilters : null,
    );
});
const openAdd = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.opening_balance = 0;
    createForm.location_id = '';
    addOpen.value = true;
};

const createItem = () => {
    if (!props.canWrite || !props.canEditItems) return;
    createForm.post(`/events/${event.value.id}/credentials/entitlements`, {
        onError: (errors) =>
            toastFormErrors(createForm, errors, { showError, showFormError }),
        preserveScroll: true,
        onSuccess: () => {
            addOpen.value = false;
        },
    });
};
</script>

<template>
    <AppLayout
        :title="$t('credentials.entitlements.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <div class="mb-7">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('credentials.entitlements.title') }}
                </h1>
                <p class="mt-1 mb-0 max-w-3xl text-sm text-muted">
                    {{ $t('credentials.entitlements.lead') }}
                </p>
            </div>

            <section aria-labelledby="entitlement-items-heading">
                <div
                    class="mb-3 flex flex-wrap items-start justify-between gap-4"
                >
                    <div>
                        <h2
                            id="entitlement-items-heading"
                            class="m-0 text-lg font-bold"
                        >
                            {{ $t('credentials.entitlements.items.title') }}
                        </h2>
                        <p class="mt-1 mb-0 max-w-3xl text-sm text-muted">
                            {{ $t('credentials.entitlements.items.lead') }}
                        </p>
                    </div>
                    <Button
                        v-if="canWrite && canEditItems"
                        type="button"
                        class="min-h-10"
                        @click="openAdd"
                    >
                        <Icon
                            :name="['fas', 'plus']"
                            size="sm"
                        />
                        {{ $t('credentials.entitlements.actions.add') }}
                    </Button>
                </div>

                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <form
                        class="relative w-full sm:w-64"
                        role="search"
                        @submit.prevent
                    >
                        <Icon
                            :name="['fas', 'magnifying-glass']"
                            class="pointer-events-none absolute top-3.5 left-3 z-10 text-muted"
                            size="sm"
                        />
                        <Input
                            id="entitlement-search"
                            v-model="search"
                            type="search"
                            class="min-h-11 pl-9"
                            :aria-label="
                                $t('credentials.entitlements.items.search')
                            "
                            :placeholder="
                                $t('credentials.entitlements.items.search')
                            "
                        />
                    </form>
                    <div class="w-full sm:w-72">
                        <LabelCombobox
                            v-model="selectedLabelIds"
                            :labels="labels"
                            :placeholder="
                                $t('credentials.entitlements.labels.filter')
                            "
                            :aria-label="
                                $t('credentials.entitlements.labels.filter')
                            "
                        />
                    </div>
                    <Button
                        v-if="hasFilters"
                        type="button"
                        variant="ghost"
                        @click="clearFilters"
                        >{{
                            $t('credentials.entitlements.items.clear_filters')
                        }}</Button
                    >
                </div>

                <DataTable
                    ref="table"
                    :columns="columns"
                    :data="items"
                    :options="options"
                >
                    <template #labelsCell="{ cellData }">
                        <div class="flex flex-wrap gap-1.5">
                            <Tag
                                v-for="label in cellData"
                                :key="label.id"
                                :name="label.name"
                                :color="label.color"
                            />
                            <span
                                v-if="!cellData.length"
                                class="text-muted"
                            >
                                {{ $t('data_table.empty_value') }}
                            </span>
                        </div>
                    </template>
                    <template #actionsCell="{ rowData }">
                        <div class="flex items-center justify-end gap-2">
                            <Link
                                :href="
                                    returnUrl(
                                        `/credentials/entitlements/${rowData.id}`,
                                    )
                                "
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
            </section>

            <p
                v-if="!canWrite"
                class="mt-3 mb-0 text-sm text-muted"
            >
                {{ $t('credentials.entitlements.read_only') }}
            </p>
        </div>

        <Popup
            v-if="canWrite && canEditItems"
            v-model:open="addOpen"
            :title="$t('credentials.entitlements.add.title')"
            :description="$t('credentials.entitlements.add.lead')"
            :accept-label="$t('credentials.entitlements.actions.save')"
            :cancel-label="$t('ui.dialog.cancel')"
            :busy="createForm.processing"
            @accept="createItem"
        >
            <div class="mt-5 space-y-4">
                <FormField
                    :label="$t('credentials.entitlements.fields.name')"
                    :error="createForm.errors.name"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="createForm.name"
                            :invalid="invalid"
                            :placeholder="
                                $t(
                                    'credentials.entitlements.fields.name_placeholder',
                                )
                            "
                            autocomplete="off"
                        />
                    </template>
                </FormField>
                <FormField
                    :label="$t('credentials.entitlements.labels.title')"
                    :error="createLabelError"
                >
                    <LabelCombobox
                        v-model="createForm.label_ids"
                        v-model:new-labels="createForm.new_labels"
                        :labels="labels"
                        :colors="labelColors"
                        :invalid="Boolean(createLabelError)"
                        :disabled="createForm.processing"
                        allow-create
                    />
                </FormField>
                <FormField
                    v-if="canAdjustStock"
                    :label="
                        $t('credentials.entitlements.fields.opening_balance')
                    "
                    :error="createForm.errors.opening_balance"
                    :hint="
                        $t(
                            'credentials.entitlements.fields.opening_balance_hint',
                        )
                    "
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="createForm.opening_balance"
                            :invalid="invalid"
                            type="number"
                            min="0"
                            step="1"
                        />
                    </template>
                </FormField>
                <FormField
                    v-if="canAdjustStock"
                    :label="$t('credentials.entitlements.fields.location')"
                    :error="createForm.errors.location_id"
                    :hint="
                        $t(
                            'credentials.entitlements.fields.opening_location_hint',
                        )
                    "
                    :required="Number(createForm.opening_balance) > 0"
                >
                    <template #default="{ id, invalid }">
                        <CustomDropdown
                            :id="id"
                            v-model="createForm.location_id"
                            :items="locationItems"
                            :invalid="invalid"
                            :placeholder="
                                $t(
                                    'credentials.entitlements.fields.location_placeholder',
                                )
                            "
                            :empty-text="
                                $t(
                                    'credentials.entitlements.fields.location_empty',
                                )
                            "
                        />
                    </template>
                </FormField>
            </div>
        </Popup>
    </AppLayout>
</template>
