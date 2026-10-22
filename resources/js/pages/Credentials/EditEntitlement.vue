<script setup>
import { useReturnNavigation } from '../../composables/useReturnNavigation';
import { returnFormOptions } from '../../lib/returnNavigation';
import { BackLink } from '../../components/ui/back-link';
import AppLayout from '../../layouts/AppLayout.vue';
import { EntitlementLabels } from '../../components/ui/entitlement-labels';
import { Button } from '../../components/ui/button';
import { Card, CardTitle } from '../../components/ui/card';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Switch } from '../../components/ui/switch';
import { LabelCombobox } from '../../components/ui/label-combobox';
import { Popup } from '../../components/ui/popup';
import { DataTable } from '../../components/ui/data-table';
import InventoryHistoryTable from '../../components/check-in/InventoryHistoryTable.vue';
import { Badge } from '../../components/ui/badge';
import { Dialog } from '../../components/ui/dialog';
import { credentialsHome } from '../../lib/credentialsNavigation';
import { toastFormErrors } from '../../lib/fieldError';
import { SegmentedControl } from '../../components/ui/segmented-control';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../../components/ui/table';
import { Textarea } from '../../components/ui/textarea';
import { useFlashToast } from '../../composables/useFlashToast';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const { returnUrl } = useReturnNavigation();

/* eslint-disable vue/prop-name-casing -- Inertia payload follows the locked server contract. */
const props = defineProps({
    event: { type: Object, required: true },
    item: { type: Object, required: true },
    labels: { type: Array, default: () => [] },
    labelColors: { type: Array, default: () => [] },
    stats: { type: Object, required: true },
    locations: { type: Array, default: () => [] },
    history_url: { type: String, required: true },
    history_options_url: { type: String, required: true },
    holdings_url: { type: String, required: true },
    history_export_url: { type: String, default: null },
    movementTypes: { type: Array, required: true },
    pass_usage: { type: Array, default: () => [] },
    locations_for_adjust: { type: Array, default: () => [] },
    adjust_location_id: { type: Number, default: null },
    open_adjust: { type: Boolean, default: false },
    is_read_only: { type: Boolean, default: false },
    canEditItems: { type: Boolean, default: false },
    canAdjustStock: { type: Boolean, default: false },
});
/* eslint-enable vue/prop-name-casing */

const page = usePage();
const canEdit = computed(() => props.canEditItems && !props.is_read_only);
const canAdjust = computed(() => props.canAdjustStock && !props.is_read_only);
const deleteOpen = ref(false);
const deleteForm = useForm({});
const form = useForm({
    name: props.item.name,
    needs_unique_code: props.item.needs_unique_code,
    low_stock_level: props.item.low_stock_level ?? '',
    label_ids: props.item.labels.map((label) => label.id),
    new_labels: [],
});
const adjustForm = useForm({
    location_id: props.adjust_location_id ?? '',
    direction: 'add',
    quantity: 1,
    reason: '',
});
const adjustOpen = ref(
    canAdjust.value && (props.open_adjust || props.adjust_location_id !== null),
);
const { showError, showFormError } = useFlashToast();
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    {
        label: trans('nav.credentials'),
        href: credentialsHome(page.props.permissions),
    },
    {
        label: trans('nav.credentials.entitlements'),
        href: '/credentials/entitlements',
    },
    { label: props.item.name },
]);
const adjustLocationItems = computed(() =>
    props.locations_for_adjust.map((location) => ({
        value: location.id,
        title: location.name,
    })),
);
const directionOptions = computed(() => [
    {
        value: 'add',
        label: trans('credentials.entitlements.adjust.add'),
        icon: ['fas', 'plus'],
        variant: 'primary',
    },
    {
        value: 'remove',
        label: trans('credentials.entitlements.adjust.remove'),
        icon: ['fas', 'circle-minus'],
        variant: 'danger',
    },
]);
const selectedLocation = computed(() => {
    if (adjustForm.location_id === '' || adjustForm.location_id === null) {
        return null;
    }

    return (
        props.locations.find(
            (location) => location.id === Number(adjustForm.location_id),
        ) ?? null
    );
});
const signedAdjustment = computed(() => {
    const quantity = Number(adjustForm.quantity) || 0;

    return adjustForm.direction === 'remove' ? -quantity : quantity;
});
const newLocationStock = computed(() =>
    selectedLocation.value
        ? selectedLocation.value.in_stock + signedAdjustment.value
        : null,
);
const passUsage = computed(() =>
    props.pass_usage
        .map((usage) =>
            trans('credentials.entitlements.details.pass_usage_item', {
                name: usage.pass_type_name,
                count: usage.line_count,
            }),
        )
        .join(', '),
);
const labelError = computed(
    () =>
        form.errors.label_ids ||
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('new_labels.'),
        )?.[1] ||
        '',
);

const submit = (afterSave) => {
    if (!canEdit.value) return;
    form.put(
        returnUrl(
            `/events/${props.event.id}/credentials/entitlements/${props.item.id}`,
        ),
        returnFormOptions(form, afterSave, {
            onError: (errors) =>
                toastFormErrors(form, errors, { showError, showFormError }),
        }),
    );
};

const openAdjust = (locationId = '') => {
    if (!canAdjust.value) return;
    adjustForm.reset();
    adjustForm.clearErrors();
    adjustForm.location_id = locationId || '';
    adjustForm.direction = 'add';
    adjustForm.quantity = 1;
    adjustForm.reason = '';
    adjustOpen.value = true;
};

const submitAdjustment = () => {
    if (!canAdjust.value) return;
    if (adjustForm.location_id === '' || adjustForm.location_id === null) {
        adjustForm.setError(
            'location_id',
            trans('credentials.entitlements.errors.location_required'),
        );
        showFormError(adjustForm.errors);

        return;
    }

    adjustForm.post(
        returnUrl(
            `/events/${props.event.id}/credentials/entitlements/${props.item.id}/adjustments`,
        ),
        {
            preserveScroll: true,
            onError: (errors) =>
                toastFormErrors(adjustForm, errors, {
                    showError,
                    showFormError,
                }),
            onSuccess: () => {
                adjustOpen.value = false;
            },
        },
    );
};

const destroyItem = () => {
    if (!canEdit.value) return;
    deleteForm.delete(
        returnUrl(
            `/events/${props.event.id}/credentials/entitlements/${props.item.id}`,
        ),
        {
            onError: (errors) =>
                toastFormErrors(deleteForm, errors, {
                    showError,
                    showFormError,
                }),
            onSuccess: () => {
                deleteOpen.value = false;
            },
        },
    );
};
const peopleColumns = computed(() => [
    {
        data: 'name',
        title: trans('inventory.columns.person'),
        render: { display: '#personCell' },
    },
    { data: 'balance', title: trans('inventory.columns.held') },
]);
const peopleOptions = computed(() => ({
    serverSide: true,
    searching: false,
    ordering: false,
    lengthChange: false,
    pageLength: 10,
    layout: { topStart: null, topEnd: null },
    language: { emptyTable: trans('inventory.history.no_people') },
}));
</script>

<template>
    <AppLayout
        :title="item.name"
        :breadcrumbs="breadcrumbs"
        compact-mobile-header
    >
        <div
            class="container mx-auto"
            :class="canEdit ? 'pb-24' : ''"
        >
            <BackLink
                href="/credentials/entitlements"
                :page="$t('credentials.entitlements.title')"
                :form="form"
                @save="submit"
            />
            <div class="mb-5 flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ item.name }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('credentials.entitlements.edit.lead') }}
                    </p>
                </div>
                <Button
                    v-if="canEdit"
                    variant="danger"
                    :disabled="deleteForm.processing"
                    @click="deleteOpen = true"
                    >{{ $t('credentials.entitlements.actions.delete') }}</Button
                >
            </div>

            <p
                v-if="is_read_only"
                class="mb-5 rounded-lg border border-line bg-page p-3 text-sm text-muted"
            >
                {{ $t('credentials.entitlements.read_only') }}
            </p>

            <form
                id="entitlement-details"
                @submit.prevent="submit"
            >
                <Card class="p-5 sm:p-6">
                    <CardTitle>
                        {{ $t('credentials.entitlements.details.title') }}
                    </CardTitle>
                    <p class="mt-1 mb-5 text-sm text-muted">
                        {{ $t('credentials.entitlements.details.lead') }}
                    </p>

                    <FormField
                        :label="$t('credentials.entitlements.fields.name')"
                        :error="form.errors.name"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.name"
                                :invalid="invalid"
                                :disabled="!canEdit || form.processing"
                                autocomplete="off"
                            />
                        </template>
                    </FormField>

                    <FormField
                        class="mt-5"
                        :label="$t('credentials.entitlements.labels.title')"
                        :error="labelError"
                    >
                        <LabelCombobox
                            v-model="form.label_ids"
                            v-model:new-labels="form.new_labels"
                            :labels="labels"
                            :colors="labelColors"
                            :invalid="Boolean(labelError)"
                            :disabled="!canEdit || form.processing"
                            allow-create
                        />
                    </FormField>

                    <FormField
                        class="mt-5"
                        :label="
                            $t(
                                'credentials.entitlements.fields.low_stock_level',
                            )
                        "
                        :hint="
                            $t(
                                'credentials.entitlements.fields.low_stock_level_hint',
                            )
                        "
                        :error="form.errors.low_stock_level"
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.low_stock_level"
                                type="number"
                                min="0"
                                max="2147483647"
                                step="1"
                                :invalid="invalid"
                                :disabled="!canEdit || form.processing"
                            />
                        </template>
                    </FormField>

                    <FormField
                        class="mt-5"
                        :error="form.errors.needs_unique_code"
                    >
                        <Switch
                            v-model="form.needs_unique_code"
                            :label="
                                $t(
                                    'credentials.entitlements.fields.needs_unique_code',
                                )
                            "
                            :disabled="!canEdit || form.processing"
                        />
                    </FormField>

                    <p class="mt-5 mb-0 text-xs text-muted">
                        {{
                            passUsage
                                ? $t(
                                      'credentials.entitlements.details.pass_usage',
                                      { usage: passUsage },
                                  )
                                : $t(
                                      'credentials.entitlements.details.pass_usage_empty',
                                  )
                        }}
                    </p>
                </Card>
            </form>

            <section
                class="mt-5 grid gap-3 sm:grid-cols-3"
                :aria-label="$t('credentials.entitlements.stats.title')"
            >
                <Card class="p-5">
                    <p
                        class="m-0 text-xs font-bold tracking-wide text-muted uppercase"
                    >
                        {{ $t('credentials.entitlements.stats.in_stock') }}
                    </p>
                    <p
                        class="mt-1 mb-0 text-2xl font-bold text-primary tabular-nums"
                    >
                        {{ stats.in_stock }}
                    </p>
                    <p class="mt-1 mb-0 text-xs text-muted">
                        {{ $t('credentials.entitlements.stats.in_stock_hint') }}
                    </p>
                </Card>
                <Card class="p-5">
                    <p
                        class="m-0 text-xs font-bold tracking-wide text-muted uppercase"
                    >
                        {{ $t('credentials.entitlements.stats.expected') }}
                    </p>
                    <p
                        class="mt-1 mb-0 text-2xl font-bold text-secondary tabular-nums"
                    >
                        {{ stats.expected }}
                    </p>
                    <p class="mt-1 mb-0 text-xs text-muted">
                        {{ $t('credentials.entitlements.stats.expected_hint') }}
                    </p>
                </Card>
                <Card class="p-5">
                    <p
                        class="m-0 text-xs font-bold tracking-wide text-muted uppercase"
                    >
                        {{ $t('credentials.entitlements.stats.issued') }}
                    </p>
                    <p class="mt-1 mb-0 text-2xl font-bold tabular-nums">
                        {{ stats.issued }}
                    </p>
                    <p class="mt-1 mb-0 text-xs text-muted">
                        {{ $t('credentials.entitlements.stats.issued_hint') }}
                    </p>
                </Card>
            </section>

            <section
                class="mt-7"
                aria-labelledby="entitlement-locations-heading"
            >
                <div class="flex items-start justify-between gap-4">
                    <h2
                        id="entitlement-locations-heading"
                        class="m-0 text-lg font-bold"
                    >
                        {{ $t('credentials.entitlements.locations.title') }}
                    </h2>
                    <Button
                        v-if="canAdjust"
                        type="button"
                        @click="openAdjust()"
                    >
                        {{ $t('credentials.entitlements.actions.adjust') }}
                    </Button>
                </div>
                <p class="mt-1 mb-3 text-sm text-muted">
                    {{ $t('credentials.entitlements.locations.lead') }}
                </p>
                <Table>
                    <TableHeader>
                        <TableRow variant="header">
                            <TableHead>{{
                                $t(
                                    'credentials.entitlements.locations.table.location',
                                )
                            }}</TableHead>
                            <TableHead>{{
                                $t(
                                    'credentials.entitlements.locations.table.in_stock',
                                )
                            }}</TableHead>
                            <TableHead class="text-right">{{
                                $t(
                                    'credentials.entitlements.locations.table.actions',
                                )
                            }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="location in locations"
                            :key="location.id"
                        >
                            <TableCell class="font-semibold">
                                {{ location.name }}
                            </TableCell>
                            <TableCell class="font-semibold tabular-nums">
                                {{ location.in_stock }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    v-if="canAdjust && location.id !== null"
                                    type="button"
                                    size="sm"
                                    variant="outline-secondary"
                                    @click="openAdjust(location.id)"
                                >
                                    {{
                                        $t(
                                            'credentials.entitlements.actions.adjust',
                                        )
                                    }}
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="locations.length === 0">
                            <TableCell
                                colspan="3"
                                class="py-8 text-center text-sm text-muted"
                            >
                                {{
                                    $t(
                                        'credentials.entitlements.locations.empty',
                                    )
                                }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p class="mt-2 mb-0 text-xs text-muted">
                    {{ $t('credentials.entitlements.locations.hint') }}
                </p>
            </section>

            <Card class="mt-7">
                <CardTitle class="mb-2">{{
                    $t('inventory.history.held_by_people')
                }}</CardTitle>
                <p class="mb-4 text-sm text-muted">
                    {{
                        $t('inventory.history.people_total', {
                            count: stats.held_by_people ?? 0,
                        })
                    }}
                </p>
                <DataTable
                    :ajax="{
                        url: holdings_url,
                        data: (data) => {
                            data.search = '';
                        },
                    }"
                    :columns="peopleColumns"
                    :options="peopleOptions"
                >
                    <template #personCell="{ rowData }">
                        {{ rowData.name }}
                        <Badge
                            v-if="rowData.inactive"
                            pill
                            variant="warning"
                            class="ml-2"
                            >{{ $t('inventory.inactive') }}</Badge
                        >
                    </template>
                </DataTable>
            </Card>

            <Card class="mt-7">
                <CardTitle class="mb-4">{{
                    $t('inventory.history.movements')
                }}</CardTitle>
                <InventoryHistoryTable
                    :endpoint="history_url"
                    :options-endpoint="history_options_url"
                    :export-url="history_export_url"
                    :timezone="event.timezone"
                    :items="[item]"
                    :item-id="item.id"
                    :movement-types="movementTypes"
                />
            </Card>

            <div
                v-if="canEdit"
                class="fixed right-0 bottom-0 left-0 z-30 border-t border-line bg-ground/95 py-3 backdrop-blur lg:left-[var(--app-sidebar-width)]"
            >
                <div class="container mx-auto px-4 md:px-6">
                    <div
                        class="mx-auto flex items-center justify-between gap-2"
                    >
                        <Button
                            href="/credentials/entitlements"
                            variant="cancel"
                            :disabled="form.processing"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <Button
                            form="entitlement-details"
                            type="submit"
                            :loading="form.processing"
                        >
                            {{ $t('credentials.entitlements.actions.save') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <Dialog
            v-if="canEdit"
            v-model:open="deleteOpen"
            :title="$t('credentials.entitlements.delete.title')"
            :description="
                $t('credentials.entitlements.delete.description', {
                    name: item.name,
                })
            "
            :confirm-label="$t('credentials.entitlements.actions.delete')"
            :cancel-label="$t('ui.dialog.cancel')"
            :busy="deleteForm.processing"
            @confirm="destroyItem"
        >
            <EntitlementLabels
                :labels="item.labels ?? []"
                :item-name="item.name"
                class="mt-3"
            />
        </Dialog>
        <Popup
            v-if="canAdjust"
            v-model:open="adjustOpen"
            :title="$t('credentials.entitlements.adjust.title')"
            :description="$t('credentials.entitlements.adjust.lead')"
            :accept-label="
                $t('credentials.entitlements.actions.apply_adjustment')
            "
            :cancel-label="$t('ui.dialog.cancel')"
            :busy="adjustForm.processing"
            @accept="submitAdjustment"
        >
            <div class="mt-5 space-y-4">
                <FormField
                    :label="$t('credentials.entitlements.fields.location')"
                    :error="adjustForm.errors.location_id"
                    required
                >
                    <template #default="{ id, invalid }">
                        <CustomDropdown
                            :id="id"
                            v-model="adjustForm.location_id"
                            :items="adjustLocationItems"
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
                            :invalid="invalid"
                        />
                    </template>
                </FormField>
                <p
                    v-if="locations_for_adjust.length === 0"
                    class="m-0 pb-2 text-sm text-muted"
                >
                    {{ $t('credentials.entitlements.fields.location_missing') }}
                    <a
                        v-if="
                            page.props.auth?.user?.is_admin ||
                            page.props.permissions?.['inventory.manage']
                        "
                        href="/settings/locations"
                        class="text-secondary hover:underline"
                    >
                        {{
                            $t(
                                'credentials.entitlements.fields.location_settings',
                            )
                        }}
                    </a>
                </p>
                <FormField
                    :label="$t('credentials.entitlements.fields.direction')"
                    :error="adjustForm.errors.direction"
                    required
                >
                    <template #default>
                        <SegmentedControl
                            v-model="adjustForm.direction"
                            :options="directionOptions"
                            :aria-label="
                                $t('credentials.entitlements.fields.direction')
                            "
                            equal
                            unselected-class="bg-ground text-muted hover:text-charcoal"
                            class="w-full"
                        />
                    </template>
                </FormField>
                <FormField
                    :label="$t('credentials.entitlements.fields.quantity')"
                    :error="adjustForm.errors.quantity"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="adjustForm.quantity"
                            :invalid="invalid"
                            type="number"
                            min="1"
                            step="1"
                        />
                    </template>
                </FormField>
                <FormField
                    :label="$t('credentials.entitlements.fields.reason')"
                    :error="adjustForm.errors.reason"
                    :required="adjustForm.direction === 'remove'"
                >
                    <template #default="{ id, invalid }">
                        <Textarea
                            :id="id"
                            v-model="adjustForm.reason"
                            :invalid="invalid"
                            :placeholder="
                                $t(
                                    'credentials.entitlements.fields.reason_placeholder',
                                )
                            "
                            maxlength="255"
                        />
                    </template>
                </FormField>
                <div class="rounded-lg border border-line bg-page p-3">
                    <p class="m-0 text-xs font-bold text-muted uppercase">
                        {{
                            $t('credentials.entitlements.adjust.preview.title')
                        }}
                    </p>
                    <p class="mt-1 mb-0 text-sm font-semibold">
                        {{
                            selectedLocation
                                ? $t(
                                      'credentials.entitlements.adjust.preview.value',
                                      {
                                          location: selectedLocation.name,
                                          quantity: newLocationStock,
                                      },
                                  )
                                : $t(
                                      'credentials.entitlements.adjust.preview.empty',
                                  )
                        }}
                    </p>
                </div>
            </div>
        </Popup>
    </AppLayout>
</template>
