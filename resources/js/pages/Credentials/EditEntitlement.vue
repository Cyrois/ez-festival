<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Popup } from '../../components/ui/popup';
import { Select } from '../../components/ui/select';
import { SegmentedControl } from '../../components/ui/segmented-control';
import { Tag } from '../../components/ui/tag';
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
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

/* eslint-disable vue/prop-name-casing -- Inertia payload follows the locked server contract. */
const props = defineProps({
    event: { type: Object, required: true },
    item: { type: Object, required: true },
    labels: { type: Array, default: () => [] },
    labelColors: { type: Array, default: () => [] },
    stats: { type: Object, required: true },
    locations: { type: Array, default: () => [] },
    issued_log: { type: Array, default: () => [] },
    pass_usage: { type: Array, default: () => [] },
    locations_for_adjust: { type: Array, default: () => [] },
    adjust_location_id: { type: Number, default: null },
    open_adjust: { type: Boolean, default: false },
    is_read_only: { type: Boolean, default: false },
});
/* eslint-enable vue/prop-name-casing */

const form = useForm({
    name: props.item.name,
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
    !props.is_read_only &&
        (props.open_adjust || props.adjust_location_id !== null),
);
const { showFormError } = useFlashToast();
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.credentials'), href: '/credentials/passes' },
    {
        label: trans('nav.credentials.entitlements'),
        href: '/credentials/entitlements',
    },
    { label: props.item.name },
]);
const adjustLocationItems = computed(() => [
    {
        value: '',
        title: trans('credentials.entitlements.locations.unassigned'),
    },
    ...props.locations_for_adjust.map((location) => ({
        value: location.id,
        title: location.name,
    })),
]);
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
        return (
            props.locations.find((location) => location.id === null) ?? {
                id: null,
                name: trans('credentials.entitlements.locations.unassigned'),
                in_stock: 0,
            }
        );
    }

    return props.locations.find(
        (location) => location.id === Number(adjustForm.location_id),
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

const toggleLabel = (id) => {
    if (props.is_read_only) return;

    form.label_ids = form.label_ids.includes(id)
        ? form.label_ids.filter((value) => value !== id)
        : [...form.label_ids, id];
};

const submit = () => {
    form.put(
        `/events/${props.event.id}/credentials/entitlements/${props.item.id}`,
        { onError: showFormError },
    );
};

const openAdjust = (locationId = '') => {
    adjustForm.reset();
    adjustForm.clearErrors();
    adjustForm.location_id = locationId ?? '';
    adjustForm.direction = 'add';
    adjustForm.quantity = 1;
    adjustForm.reason = '';
    adjustOpen.value = true;
};

const submitAdjustment = () => {
    adjustForm
        .transform((data) => ({
            ...data,
            location_id: data.location_id === '' ? null : data.location_id,
        }))
        .post(
            `/events/${props.event.id}/credentials/entitlements/${props.item.id}/adjustments`,
            {
                preserveScroll: true,
                onError: showFormError,
                onSuccess: () => {
                    adjustOpen.value = false;
                },
            },
        );
};

const formatWhen = (value) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
</script>

<template>
    <AppLayout
        :title="item.name"
        :breadcrumbs="breadcrumbs"
        back-href="/credentials/entitlements"
        :back-label="$t('credentials.entitlements.view.back')"
    >
        <div
            class="container mx-auto max-w-6xl"
            :class="is_read_only ? '' : 'pb-24'"
        >
            <div class="mb-5">
                <div>
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ item.name }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('credentials.entitlements.edit.lead') }}
                    </p>
                </div>
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
                    <h2 class="m-0 text-lg font-bold">
                        {{ $t('credentials.entitlements.details.title') }}
                    </h2>
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
                                :disabled="is_read_only || form.processing"
                                autocomplete="off"
                            />
                        </template>
                    </FormField>

                    <fieldset class="mt-5 min-w-0 border-0 p-0">
                        <legend class="mb-2 text-xs font-bold text-charcoal">
                            {{ $t('credentials.entitlements.labels.title') }}
                        </legend>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="label in labels"
                                :key="label.id"
                                type="button"
                                class="rounded-full focus-visible:ring-[3px] focus-visible:ring-primary/35 focus-visible:outline-none disabled:cursor-default"
                                :class="
                                    form.label_ids.includes(label.id)
                                        ? 'ring-2 ring-primary ring-offset-2'
                                        : 'opacity-60'
                                "
                                :aria-pressed="
                                    form.label_ids.includes(label.id)
                                "
                                :disabled="is_read_only || form.processing"
                                @click="toggleLabel(label.id)"
                            >
                                <Tag
                                    :name="label.name"
                                    :color="label.color"
                                />
                            </button>
                        </div>
                        <p
                            v-if="form.errors.label_ids"
                            class="mt-2 mb-0 text-xs text-danger"
                            role="alert"
                        >
                            {{ form.errors.label_ids }}
                        </p>

                        <div
                            v-for="(label, index) in form.new_labels"
                            :key="index"
                            class="mt-3 grid items-start gap-3 rounded-lg border border-line bg-page p-3 sm:grid-cols-[1fr_10rem_auto]"
                        >
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="
                                    $t('credentials.entitlements.labels.name')
                                "
                                :error="form.errors[`new_labels.${index}.name`]"
                            >
                                <Input
                                    :id="id"
                                    v-model="label.name"
                                    :invalid="invalid"
                                    :disabled="form.processing"
                                    maxlength="255"
                                    required
                                />
                            </FormField>
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="
                                    $t('credentials.entitlements.labels.color')
                                "
                                :error="
                                    form.errors[`new_labels.${index}.color`]
                                "
                            >
                                <Select
                                    :id="id"
                                    v-model="label.color"
                                    :invalid="invalid"
                                    :disabled="form.processing"
                                >
                                    <option
                                        v-for="color in labelColors"
                                        :key="color"
                                        :value="color"
                                    >
                                        {{
                                            $t(
                                                `credentials.entitlements.labels.colors.${color}`,
                                            )
                                        }}
                                    </option>
                                </Select>
                            </FormField>
                            <Button
                                type="button"
                                variant="ghost"
                                class="sm:mt-5"
                                :aria-label="
                                    $t(
                                        'credentials.entitlements.labels.remove',
                                        { name: label.name },
                                    )
                                "
                                :disabled="form.processing"
                                @click="form.new_labels.splice(index, 1)"
                            >
                                <Icon :name="['fas', 'trash']" />
                            </Button>
                        </div>
                        <Button
                            v-if="!is_read_only"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="mt-3"
                            :disabled="
                                form.processing || form.new_labels.length >= 20
                            "
                            @click="
                                form.new_labels.push({
                                    name: '',
                                    color: 'primary',
                                })
                            "
                        >
                            <Icon
                                :name="['fas', 'plus']"
                                size="sm"
                            />
                            {{ $t('credentials.entitlements.labels.add') }}
                        </Button>
                    </fieldset>

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
                        v-if="!is_read_only"
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
                            :key="location.id ?? 'unassigned'"
                        >
                            <TableCell class="font-semibold">
                                {{ location.name }}
                            </TableCell>
                            <TableCell class="font-semibold tabular-nums">
                                {{ location.in_stock }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    v-if="!is_read_only"
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

            <section
                class="mt-7"
                aria-labelledby="issued-log-heading"
            >
                <h2
                    id="issued-log-heading"
                    class="m-0 text-lg font-bold"
                >
                    {{ $t('credentials.entitlements.view.issued') }}
                </h2>
                <p class="mt-1 mb-3 text-sm text-muted">
                    {{ $t('credentials.entitlements.view.issued_lead') }}
                </p>
                <Table>
                    <TableHeader>
                        <TableRow variant="header">
                            <TableHead>{{
                                $t('credentials.entitlements.view.table.when')
                            }}</TableHead>
                            <TableHead>{{
                                $t('credentials.entitlements.view.table.pass')
                            }}</TableHead>
                            <TableHead>{{
                                $t('credentials.entitlements.view.table.code')
                            }}</TableHead>
                            <TableHead>{{
                                $t(
                                    'credentials.entitlements.view.table.issued_by',
                                )
                            }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="issue in issued_log"
                            :key="issue.id"
                        >
                            <TableCell>{{ formatWhen(issue.when) }}</TableCell>
                            <TableCell>{{
                                issue.pass_name ??
                                $t('credentials.entitlements.view.not_recorded')
                            }}</TableCell>
                            <TableCell class="font-mono">{{
                                issue.code ??
                                $t('credentials.entitlements.view.not_recorded')
                            }}</TableCell>
                            <TableCell>{{
                                issue.issued_by?.name ??
                                $t('credentials.entitlements.view.not_recorded')
                            }}</TableCell>
                        </TableRow>
                        <TableRow v-if="issued_log.length === 0">
                            <TableCell
                                colspan="4"
                                class="py-8 text-center text-sm text-muted"
                            >
                                {{
                                    $t(
                                        'credentials.entitlements.view.issued_empty',
                                    )
                                }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p class="mt-2 mb-0 text-xs text-muted">
                    {{ $t('credentials.entitlements.view.issued_hint') }}
                </p>
            </section>

            <div
                v-if="!is_read_only"
                class="fixed right-0 bottom-0 left-0 z-30 border-t border-line bg-ground/95 py-3 backdrop-blur lg:left-56"
            >
                <div class="container mx-auto px-4 md:px-6">
                    <div
                        class="mx-auto flex max-w-6xl items-center justify-between gap-2"
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

        <Popup
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
