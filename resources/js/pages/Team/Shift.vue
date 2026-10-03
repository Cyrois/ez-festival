<script setup>
import { ColorPicker } from '../../components/ui/color-picker';
import ShiftRoleSlots from '../../components/team/ShiftRoleSlots.vue';
import ShiftRoster from '../../components/team/ShiftRoster.vue';
import ShiftAssignDialog from '../../components/team/ShiftAssignDialog.vue';
import {
    draftShiftSlots,
    shiftSlotPayload,
    shiftSlotErrors,
} from '../../lib/shiftRoleSlots';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { Dialog } from '../../components/ui/dialog';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import AppLayout from '../../layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { scheduleReturnHref } from '../../lib/scheduleTimeline';

const props = defineProps({
    event: { type: Object, required: true },
    shift: { type: Object, required: true },
    locations: { type: Array, required: true },
    labelColors: { type: Array, required: true },
    roles: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    returnContext: { type: Object, default: () => ({}) },
});

const form = useForm({
    color: props.shift.color ?? 'teal',
    name: props.shift.name ?? '',
    location_id: props.shift.location_id,
    starts_at: props.shift.starts_at,
    ends_at: props.shift.ends_at,
    slots: draftShiftSlots(props.shift.slots),
    ...props.returnContext,
});
const backHref = computed(() => scheduleReturnHref(props.returnContext));
const slotErrors = ref({});
const clearSlotError = (key, field) => {
    if (slotErrors.value[key]) delete slotErrors.value[key][field];
};
const deleting = ref(false);
const deleteBusy = ref(false);
const confirmationCount = ref(0);
const selectedSlot = ref(null);
const assignmentBusy = ref(false);
const assignmentCounts = computed(() =>
    Object.fromEntries(
        props.shift.slots.map((slot) => [slot.id, slot.assigned_count]),
    ),
);
const unsaved = computed(() => form.isDirty);
const assignmentReason = computed(() =>
    trans(
        props.event.is_locked
            ? 'team.scheduling.locked'
            : !props.canManage
              ? 'team.scheduling.no_permission'
              : unsaved.value
                ? 'team.scheduling.assignments.save_first'
                : '',
    ),
);
const assignmentsEnabled = computed(
    () =>
        canWrite.value &&
        !unsaved.value &&
        !form.processing &&
        !assignmentBusy.value,
);
const openDelete = () => {
    confirmationCount.value = props.shift.assignment_count;
    deleting.value = true;
};
const removeAssignment = (assignment) => {
    if (!assignmentsEnabled.value) return;
    assignmentBusy.value = true;
    router.delete(
        `/team/events/${props.event.id}/shifts/${props.shift.id}/assignments/${assignment.id}`,
        {
            data: props.returnContext,
            preserveScroll: true,
            onError: (errors) => showFormError(errors),
            onFinish: () => {
                assignmentBusy.value = false;
            },
        },
    );
};
const { showError, showFormError } = useFlashToast();

const canWrite = computed(() => props.canManage && !props.event.is_locked);
const displayName = computed(
    () => props.shift.name || trans('team.scheduling.unnamed_shift'),
);
const locationItems = computed(() =>
    props.locations.map((location) => ({
        value: location.id,
        title: location.name,
    })),
);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    {
        label: trans('nav.team.scheduling'),
        href: backHref.value,
    },
    { label: displayName.value },
]);

const submit = () => {
    if (!canWrite.value || form.processing) return;

    const submitted = [...form.slots];
    form.transform((data) => ({
        ...data,
        slots: shiftSlotPayload(data.slots),
    })).put('/team/events/' + props.event.id + '/shifts/' + props.shift.id, {
        preserveScroll: true,
        onError: (errors) => {
            slotErrors.value = shiftSlotErrors(submitted, errors);
            if (Object.keys(errors).some((key) => key.startsWith('slots'))) {
                showFormError(errors);
            } else {
                toastFormErrors(form, errors, { showError, showFormError });
            }
        },
        onSuccess: () => {
            form.slots = draftShiftSlots(props.shift.slots);
            form.defaults();
            slotErrors.value = {};
        },
    });
};

const destroy = () => {
    if (!canWrite.value) return;

    deleteBusy.value = true;
    router.delete(
        '/team/events/' + props.event.id + '/shifts/' + props.shift.id,
        {
            data: {
                assignment_count: confirmationCount.value,
                ...props.returnContext,
            },
            onError: (errors) => {
                showFormError(errors);
                if (errors.assignment_count) {
                    router.reload({
                        only: ['shift'],
                        onSuccess: () => {
                            confirmationCount.value =
                                props.shift.assignment_count;
                        },
                    });
                }
            },
            onFinish: () => {
                deleteBusy.value = false;
            },
        },
    );
};
</script>

<template>
    <AppLayout
        :title="displayName"
        :breadcrumbs="breadcrumbs"
        :back-href="backHref"
        :back-label="$t('team.scheduling.actions.back')"
    >
        <div class="container mx-auto max-w-6xl pb-24 xl:max-w-none">
            <header class="mb-5 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ displayName }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('team.scheduling.shift_lead') }}
                    </p>
                </div>
                <Button
                    v-if="canWrite"
                    type="button"
                    variant="outline-danger"
                    class="shrink-0"
                    @click="openDelete"
                >
                    <Icon
                        :name="['fas', 'trash-can']"
                        size="sm"
                    />
                    {{ $t('team.scheduling.actions.delete') }}
                </Button>
            </header>

            <p
                v-if="event.is_locked"
                class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                <Icon :name="['fas', 'lock']" />
                {{ $t('team.scheduling.locked') }}
            </p>
            <p
                v-else-if="!canManage"
                class="mb-4 rounded-lg border border-line bg-page p-3 text-sm text-muted"
                role="status"
            >
                {{ $t('team.scheduling.no_permission') }}
            </p>

            <form
                id="shift-details-form"
                class="grid items-start gap-4 xl:grid-cols-2 xl:items-stretch"
                novalidate
                @submit.prevent="submit"
            >
                <Card class="min-w-0">
                    <h2 class="m-0 mb-4 text-xl font-bold text-muted">
                        {{ $t('team.scheduling.shift_section') }}
                    </h2>

                    <dl
                        v-if="!canWrite"
                        class="grid gap-4 sm:grid-cols-2"
                    >
                        <div
                            v-for="field in [
                                'name',
                                'color',
                                'location',
                                'starts_at',
                                'ends_at',
                            ]"
                            :key="field"
                        >
                            <dt class="text-xs font-bold text-muted">
                                {{
                                    $t(
                                        `team.scheduling.fields.${field === 'starts_at' ? 'start' : field === 'ends_at' ? 'end' : field}`,
                                    )
                                }}
                            </dt>
                            <dd class="mt-1 text-sm">
                                {{
                                    field === 'color'
                                        ? $t(`labels.colors.${shift.color}`)
                                        : shift[field] ||
                                          $t('data_table.empty_value')
                                }}
                            </dd>
                        </div>
                    </dl>
                    <div
                        v-else
                        class="space-y-4"
                    >
                        <FormField
                            :label="$t('team.scheduling.fields.name')"
                            :error="fieldError(form, 'name')"
                            required
                        >
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="form.name"
                                    :invalid="invalid"
                                    :placeholder="
                                        $t(
                                            'team.scheduling.fields.name_placeholder',
                                        )
                                    "
                                    :disabled="!canWrite || form.processing"
                                />
                            </template>
                        </FormField>

                        <FormField
                            :label="$t('team.scheduling.fields.color')"
                            :error="fieldError(form, 'color')"
                        >
                            <template #default="{ id, invalid }">
                                <ColorPicker
                                    :id="id"
                                    v-model="form.color"
                                    :colors="labelColors"
                                    :aria-label="
                                        $t('team.scheduling.fields.color')
                                    "
                                    :invalid="invalid"
                                    :disabled="form.processing"
                                />
                            </template>
                        </FormField>

                        <FormField
                            :label="$t('team.scheduling.fields.location')"
                            :error="fieldError(form, 'location_id')"
                            required
                        >
                            <template #default="{ id, invalid }">
                                <CustomDropdown
                                    :id="id"
                                    v-model="form.location_id"
                                    :items="locationItems"
                                    :invalid="invalid"
                                    :disabled="!canWrite || form.processing"
                                    :placeholder="
                                        $t(
                                            'team.scheduling.fields.location_placeholder',
                                        )
                                    "
                                />
                            </template>
                        </FormField>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField
                                :label="$t('team.scheduling.fields.start')"
                                :error="fieldError(form, 'starts_at')"
                                required
                            >
                                <template #default="{ id, invalid }">
                                    <Input
                                        :id="id"
                                        v-model="form.starts_at"
                                        type="datetime-local"
                                        :max="form.ends_at || undefined"
                                        :invalid="invalid"
                                        :disabled="!canWrite || form.processing"
                                    />
                                </template>
                            </FormField>
                            <FormField
                                :label="$t('team.scheduling.fields.end')"
                                :error="fieldError(form, 'ends_at')"
                                required
                            >
                                <template #default="{ id, invalid }">
                                    <Input
                                        :id="id"
                                        v-model="form.ends_at"
                                        type="datetime-local"
                                        :min="form.starts_at || undefined"
                                        :invalid="invalid"
                                        :disabled="!canWrite || form.processing"
                                    />
                                </template>
                            </FormField>
                        </div>
                    </div>
                </Card>

                <Card class="min-w-0">
                    <ShiftRoleSlots
                        v-model="form.slots"
                        :roles="roles"
                        :errors="slotErrors"
                        :assignment-counts="assignmentCounts"
                        :editable="canWrite"
                        :busy="form.processing"
                        :title="$t('team.scheduling.slots.detail_title')"
                        :disabled-reason="
                            $t(
                                event.is_locked
                                    ? 'team.scheduling.locked'
                                    : 'team.scheduling.no_permission',
                            )
                        "
                        @clear-error="clearSlotError"
                    />
                    <p
                        v-if="form.errors.slots"
                        class="text-sm text-danger"
                        role="alert"
                    >
                        {{ form.errors.slots }}
                    </p>
                </Card>
            </form>
            <Card class="mt-4">
                <ShiftRoster
                    :shift="shift"
                    :enabled="assignmentsEnabled"
                    :can-remove="canWrite"
                    :disabled-reason="assignmentReason"
                    :busy="assignmentBusy || form.processing"
                    @assign="selectedSlot = $event"
                    @remove="removeAssignment"
                />
            </Card>
        </div>

        <div
            v-if="canWrite"
            class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-ground py-4 lg:left-[var(--app-sidebar-width)]"
        >
            <div class="container mx-auto px-4 md:px-6 xl:px-0">
                <div
                    class="mx-auto flex max-w-6xl items-center justify-between gap-3 xl:max-w-none"
                >
                    <Button
                        :href="backHref"
                        variant="cancel"
                        :disabled="form.processing"
                        >{{ $t('ui.dialog.cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        form="shift-details-form"
                        :loading="form.processing"
                        >{{ $t('team.scheduling.actions.save') }}</Button
                    >
                </div>
            </div>
        </div>

        <ShiftAssignDialog
            v-if="selectedSlot"
            :key="selectedSlot.id"
            :shift="shift"
            :event-id="event.id"
            :requirement="selectedSlot"
            :return-context="returnContext"
            @close="selectedSlot = null"
        />
        <Dialog
            v-model:open="deleting"
            :title="$t('team.scheduling.delete.title')"
            :description="
                confirmationCount
                    ? $t('team.scheduling.assignments.delete_confirmation', {
                          count: confirmationCount,
                      })
                    : $t('team.scheduling.delete.description', {
                          name: displayName,
                      })
            "
            :confirm-label="
                confirmationCount
                    ? $t('team.scheduling.assignments.remove')
                    : $t('team.scheduling.actions.delete')
            "
            :cancel-label="$t('ui.dialog.cancel')"
            :busy="deleteBusy"
            @confirm="destroy"
        />
    </AppLayout>
</template>
