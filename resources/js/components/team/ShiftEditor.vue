<script setup>
import { ColorPicker } from '../ui/color-picker';
import ShiftRoleSlots from './ShiftRoleSlots.vue';
import ShiftTimelineRoster from './ShiftTimelineRoster.vue';
import ShiftAssignmentHoursDialog from './ShiftAssignmentHoursDialog.vue';
import ShiftBreaks from './ShiftBreaks.vue';
import {
    draftShiftBreaks,
    shiftBreakPayload,
    shiftBreakErrors,
} from '../../lib/shiftBreaks';
import ShiftAssignDialog from './ShiftAssignDialog.vue';
import {
    draftShiftSlots,
    shiftSlotPayload,
    shiftSlotErrors,
    totalShiftNeeds,
} from '../../lib/shiftRoleSlots';
import { Button } from '../ui/button';
import { Card, CardTitle } from '../ui/card';
import { CustomDropdown } from '../ui/custom-dropdown';
import { Dialog } from '../ui/dialog';
import { UnsavedChangesDialog } from '../ui/unsaved-changes-dialog';
import { FormField } from '../ui/form-field';
import { Icon } from '../ui/icon';
import { Input } from '../ui/input';
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import AppLayout from '../../layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, onUnmounted } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { draftRoster, assignmentOverlapUrl } from '../../lib/shiftAssignments';
import { scheduleReturnHref } from '../../lib/scheduleTimeline';

const props = defineProps({
    event: { type: Object, required: true },
    shift: { type: Object, default: null },
    prefill: { type: Object, default: () => ({}) },
    locations: { type: Array, required: true },
    labelColors: { type: Array, required: true },
    roles: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    breakOptions: { type: Object, required: true },
    returnContext: { type: Object, default: () => ({}) },
});

const creating = computed(() => !props.shift);
const initialShift = computed(
    () =>
        props.shift ?? {
            color: 'teal',
            name: '',
            location_id:
                props.prefill.location_id ?? props.locations[0]?.id ?? '',
            starts_at: props.prefill.starts_at ?? '',
            ends_at: props.prefill.ends_at ?? '',
            slots: [],
            breaks: [],
            assignments: [],
            assignment_count: 0,
        },
);

const form = useForm({
    color: initialShift.value.color ?? 'teal',
    name: initialShift.value.name ?? '',
    location_id: initialShift.value.location_id,
    starts_at: initialShift.value.starts_at,
    ends_at: initialShift.value.ends_at,
    slots: draftShiftSlots(initialShift.value.slots),
    breaks: draftShiftBreaks(initialShift.value.breaks),
    assignment_updates: [],
    assignment_removals: [],
    assignment_additions: [],
    ...props.returnContext,
});
const backHref = computed(() => scheduleReturnHref(props.returnContext));
const copyHref = computed(
    () =>
        '/team/shifts/create?' +
        new URLSearchParams({
            copy: props.shift?.id,
            ...props.returnContext,
        }).toString(),
);
const slotErrors = ref({});
const breakErrors = ref({});
const breakEditor = ref(null);
const clearBreakError = (key, field) => {
    if (breakErrors.value[key]) delete breakErrors.value[key][field];
    form.clearErrors('breaks');
};
const clearBreakContainmentErrors = () => {
    for (const errors of Object.values(breakErrors.value))
        delete errors.starts_at;
};
const clearSlotError = (key, field) => {
    if (slotErrors.value[key]) delete slotErrors.value[key][field];
};
const deleting = ref(false);
const deleteBusy = ref(false);
const confirmationCount = ref(0);
const selectedSlot = ref(null);
const headerAssignOpen = ref(false);
const draftPeople = ref({});
const overlapPreviews = ref({});
let nextDraftId = -1;
const previewRequests = new Map();
const selectedAssignment = ref(null);
const rosterShift = computed(() =>
    draftRoster(
        {
            ...initialShift.value,
            slots: form.slots.map((slot, index) => ({
                ...slot,
                id: slot.id ?? slot._key,
                needed:
                    Number.isInteger(Number(slot.needed)) &&
                    Number(slot.needed) > 0
                        ? Number(slot.needed)
                        : 0,
                sort_order: index,
            })),
            total_needs: totalShiftNeeds(form.slots),
            starts_at:
                form.starts_at && form.ends_at > form.starts_at
                    ? form.starts_at
                    : initialShift.value.starts_at,
            ends_at:
                form.starts_at && form.ends_at > form.starts_at
                    ? form.ends_at
                    : initialShift.value.ends_at,
            name: form.name,
            color: form.color,
            location:
                props.locations.find(
                    (location) => location.id === form.location_id,
                )?.name ?? initialShift.value.location,
        },
        form.assignment_updates,
        form.assignment_removals,
        form.assignment_additions.map((row) => ({
            ...draftPeople.value[row._key],
            ...row,
            id: row._key,
            starts_at:
                row.hours_mode === 'full_shift'
                    ? form.starts_at && form.ends_at > form.starts_at
                        ? form.starts_at
                        : initialShift.value.starts_at
                    : row.starts_at,
            ends_at:
                row.hours_mode === 'full_shift'
                    ? form.starts_at && form.ends_at > form.starts_at
                        ? form.ends_at
                        : initialShift.value.ends_at
                    : row.ends_at,
        })),
    ),
);
const timelineShift = computed(() => ({
    ...rosterShift.value,
    assignments: rosterShift.value.assignments.map((row) => ({
        ...row,
        ...(overlapPreviews.value[row.id] ?? {}),
    })),
}));
const detailsDirty = computed(
    () =>
        !creating.value &&
        (['name', 'location_id', 'starts_at', 'ends_at'].some(
            (key) => form[key] !== initialShift.value[key],
        ) ||
            JSON.stringify(shiftBreakPayload(form.breaks)) !==
                JSON.stringify(
                    shiftBreakPayload(
                        draftShiftBreaks(initialShift.value.breaks),
                    ),
                )),
);

const assignmentCounts = computed(() =>
    Object.fromEntries(
        rosterShift.value.slots.map((slot) => [slot.id, slot.assigned_count]),
    ),
);
const unsaved = computed(() => form.isDirty);
const assignmentReason = computed(() =>
    trans(
        props.event.is_locked
            ? 'team.scheduling.locked'
            : !props.canManage
              ? 'team.scheduling.no_permission'
              : detailsDirty.value
                ? 'team.scheduling.assignments.save_first'
                : '',
    ),
);
const validShiftHours = computed(() =>
    Boolean(form.starts_at && form.ends_at && form.starts_at < form.ends_at),
);
const assignmentsEnabled = computed(
    () =>
        canWrite.value &&
        validShiftHours.value &&
        !detailsDirty.value &&
        !form.processing,
);
const openDelete = () => {
    confirmationCount.value = initialShift.value.assignment_count;
    deleting.value = true;
};
const rosterEnabled = computed(
    () =>
        canWrite.value &&
        !form.processing &&
        (!creating.value || validShiftHours.value),
);
const removeAssignment = (assignment) => {
    if (!rosterEnabled.value) return;
    previewRequests.get(assignment.id)?.abort();
    delete overlapPreviews.value[assignment.id];
    if (assignment.id < 0) {
        form.assignment_additions = form.assignment_additions.filter(
            (row) => row._key !== assignment.id,
        );
        delete draftPeople.value[assignment.id];
    } else {
        form.assignment_updates = form.assignment_updates.filter(
            (row) => row.id !== assignment.id,
        );
        if (!form.assignment_removals.includes(assignment.id))
            form.assignment_removals.push(assignment.id);
    }
};
const stagePerson = (data) => {
    const id = nextDraftId--;
    const { candidate, slot, ...payload } = data;
    draftPeople.value[id] = {
        name: candidate.name,
        role_id: slot.role_id,
        role_name: slot.role_name,
        shift_role_slot_id: slot.id,
        overlaps: candidate.overlaps,
        other_shifts: candidate.other_shifts ?? [],
        is_extra: false,
        team_engagement_id: candidate.id,
    };
    form.assignment_additions.push({ ...payload, _key: id });
};
const stageHours = (assignment, data) => {
    if (!rosterEnabled.value) return;
    previewRequests.get(assignment.id)?.abort();
    previewRequests.delete(assignment.id);
    const { overlaps, other_shifts, ...hours } = data;
    if (assignment.id < 0) {
        const index = form.assignment_additions.findIndex(
            (row) => row._key === assignment.id,
        );
        const { starts_at, ends_at, hours_mode, ...identity } =
            form.assignment_additions[index];
        form.assignment_additions[index] = { ...identity, ...hours };
    } else {
        form.assignment_updates = form.assignment_updates
            .filter((row) => row.id !== assignment.id)
            .concat({ id: assignment.id, ...hours });
    }
    if (overlaps)
        overlapPreviews.value[assignment.id] = {
            overlaps,
            ...(other_shifts ? { other_shifts } : {}),
        };
};
const resizeHours = async (assignment, hours) => {
    stageHours(assignment, { hours_mode: 'custom', ...hours });
    previewRequests.get(assignment.id)?.abort();
    const controller = new AbortController();
    previewRequests.set(assignment.id, controller);
    overlapPreviews.value[assignment.id] = {
        overlaps: [],
        preview_status: 'loading',
    };
    try {
        const endpoint = assignmentOverlapUrl(
            timelineShift.value,
            props.event.id,
            assignment,
        );
        const query = {
            ...hours,
            hours_mode: 'custom',
            shift_starts_at: form.starts_at,
            shift_ends_at: form.ends_at,
            ...(assignment.id < 0
                ? { team_engagement_id: assignment.team_engagement_id }
                : {}),
        };
        const response = await fetch(
            `${endpoint}?${new URLSearchParams(query)}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        );
        if (!response.ok) throw new Error('preview');
        const result = await response.json();
        if (
            previewRequests.get(assignment.id) === controller &&
            !controller.signal.aborted
        )
            overlapPreviews.value[assignment.id] = {
                overlaps: result.data,
                other_shifts:
                    result.other_shifts ?? assignment.other_shifts ?? [],
            };
    } catch (error) {
        if (
            error.name !== 'AbortError' &&
            previewRequests.get(assignment.id) === controller
        )
            overlapPreviews.value[assignment.id] = {
                overlaps: [],
                preview_status: 'failed',
            };
    }
};
onUnmounted(() => previewRequests.forEach((controller) => controller.abort()));
const { showError, showFormError } = useFlashToast();

const canWrite = computed(() => props.canManage && !props.event.is_locked);
const displayName = computed(() =>
    creating.value
        ? trans('team.scheduling.actions.new')
        : initialShift.value.name || trans('team.scheduling.unnamed_shift'),
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

const submit = (afterSave) => {
    if (!canWrite.value || form.processing) return;
    if (breakEditor.value && !breakEditor.value.validate()) {
        showFormError({
            breaks: trans('team.scheduling.breaks.errors.review'),
        });
        return;
    }

    const submitted = [...form.slots];
    const submittedBreaks = [...form.breaks];
    form.transform((data) => ({
        ...data,
        slots: shiftSlotPayload(data.slots, true),
        breaks: shiftBreakPayload(data.breaks),
        assignment_additions: data.assignment_additions.map(
            ({ _key, ...row }) => row,
        ),
    }));
    const options = {
        preserveScroll: true,
        onError: (errors) => {
            slotErrors.value = shiftSlotErrors(submitted, errors);
            breakErrors.value = shiftBreakErrors(submittedBreaks, errors);
            if (
                Object.keys(errors).some(
                    (key) =>
                        key.startsWith('slots') ||
                        key.startsWith('breaks') ||
                        key.startsWith('assignment_'),
                )
            ) {
                showFormError(errors);
            } else {
                toastFormErrors(form, errors, { showError, showFormError });
            }
        },
        onSuccess: () => {
            if (!creating.value) {
                form.slots = draftShiftSlots(initialShift.value.slots);
                form.breaks = draftShiftBreaks(initialShift.value.breaks);
            }
            form.assignment_updates = [];
            form.assignment_removals = [];
            form.assignment_additions = [];
            draftPeople.value = {};
            overlapPreviews.value = {};
            form.defaults();
            slotErrors.value = {};
            breakErrors.value = {};
            if (typeof afterSave === 'function') afterSave();
        },
    };
    const endpoint = `/team/events/${props.event.id}/shifts`;
    if (creating.value) form.post(endpoint, options);
    else form.put(`${endpoint}/${initialShift.value.id}`, options);
};

const destroy = () => {
    if (!canWrite.value) return;

    deleteBusy.value = true;
    router.delete(
        '/team/events/' + props.event.id + '/shifts/' + initialShift.value.id,
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
                                initialShift.value.assignment_count;
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
                    <p
                        v-if="!creating"
                        class="mt-1 mb-0 text-sm text-muted"
                    >
                        {{ $t('team.scheduling.shift_lead') }}
                    </p>
                </div>
                <div
                    v-if="canWrite && !creating"
                    class="flex shrink-0 flex-wrap items-center justify-end gap-2"
                >
                    <Button
                        :href="copyHref"
                        variant="secondary"
                        :disabled="form.processing"
                    >
                        <Icon
                            :name="['fas', 'copy']"
                            size="sm"
                        />
                        {{ $t('team.scheduling.actions.copy') }}
                    </Button>
                    <Button
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
                </div>
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

            <p
                v-if="creating && !locations.length"
                class="mb-4 text-sm text-muted"
                role="status"
            >
                {{ $t('team.scheduling.no_locations') }}
            </p>
            <form
                :id="creating ? 'create-shift-form' : 'shift-details-form'"
                class="grid items-start gap-4 xl:grid-cols-2 xl:items-stretch"
                novalidate
                @submit.prevent="submit"
            >
                <Card class="min-w-0">
                    <CardTitle class="mb-4">
                        {{ $t('team.scheduling.shift_section') }}
                    </CardTitle>

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
                                        ? $t(
                                              `labels.colors.${initialShift.color}`,
                                          )
                                        : initialShift[field] ||
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
                <ShiftTimelineRoster
                    v-if="!creating || validShiftHours"
                    :shift="timelineShift"
                    :enabled="rosterEnabled"
                    :assign-enabled="assignmentsEnabled"
                    :can-manage="canWrite"
                    :disabled-reason="assignmentReason"
                    @assign="selectedSlot = $event"
                    @edit="rosterEnabled && (selectedAssignment = $event)"
                    @remove="removeAssignment"
                    @resize="resizeHours($event.assignment, $event.hours)"
                >
                    <template #footer-actions>
                        <span
                            :title="
                                assignmentReason ||
                                (!timelineShift.slots.length
                                    ? $t(
                                          'team.scheduling.assignments.no_requirements',
                                      )
                                    : '')
                            "
                        >
                            <Button
                                size="sm"
                                variant="ghost"
                                :disabled="
                                    !assignmentsEnabled ||
                                    !timelineShift.slots.length
                                "
                                @click="headerAssignOpen = true"
                            >
                                <Icon :name="['fas', 'plus']" />
                                {{
                                    $t(
                                        'team.scheduling.assignments.override_assign',
                                    )
                                }}
                            </Button>
                        </span>
                    </template>
                </ShiftTimelineRoster>
                <template v-else>
                    <CardTitle class="mb-2">{{
                        $t('team.scheduling.assignments.roster')
                    }}</CardTitle>
                    <p
                        class="text-sm text-muted"
                        role="status"
                    >
                        {{ $t('team.scheduling.roster.enter_hours') }}
                    </p>
                </template>
            </Card>
            <div class="mt-4 grid items-start gap-4 xl:grid-cols-2">
                <Card class="min-w-0">
                    <ShiftBreaks
                        ref="breakEditor"
                        v-model="form.breaks"
                        :options="breakOptions"
                        :starts-at="form.starts_at"
                        :ends-at="form.ends_at"
                        :errors="breakErrors"
                        :collection-error="form.errors.breaks"
                        :editable="canWrite"
                        :busy="form.processing"
                        :disabled-reason="
                            $t(
                                event.is_locked
                                    ? 'team.scheduling.locked'
                                    : 'team.scheduling.no_permission',
                            )
                        "
                        @clear-error="clearBreakError"
                        @clear-containment-errors="clearBreakContainmentErrors"
                    />
                </Card>
            </div>
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
                    <div class="flex items-center gap-3">
                        <span
                            v-if="!creating && unsaved"
                            class="flex items-center gap-2 text-sm text-warning"
                            role="status"
                            :title="$t('team.scheduling.unsaved_changes')"
                            data-save-reminder
                        >
                            <Icon :name="['fas', 'circle-exclamation']" />
                            <span class="sr-only sm:not-sr-only">{{
                                $t('team.scheduling.unsaved_changes')
                            }}</span>
                        </span>
                        <Button
                            type="submit"
                            :form="
                                creating
                                    ? 'create-shift-form'
                                    : 'shift-details-form'
                            "
                            :loading="form.processing"
                            :disabled="
                                form.processing ||
                                (creating && !locations.length)
                            "
                            >{{
                                $t(
                                    creating
                                        ? 'team.scheduling.actions.create'
                                        : 'team.scheduling.actions.save',
                                )
                            }}</Button
                        >
                    </div>
                </div>
            </div>
        </div>

        <ShiftAssignDialog
            v-if="selectedSlot || headerAssignOpen"
            :key="selectedSlot?.id ?? 'header'"
            :shift="timelineShift"
            :event-id="event.id"
            :requirement="selectedSlot"
            :return-context="returnContext"
            deferred
            :pending-member-ids="
                form.assignment_additions.map((row) => row.team_engagement_id)
            "
            @assigned="stagePerson"
            @close="
                selectedSlot = null;
                headerAssignOpen = false;
            "
        />
        <ShiftAssignmentHoursDialog
            v-if="selectedAssignment"
            :key="selectedAssignment.id"
            :shift="timelineShift"
            :assignment="selectedAssignment"
            :enabled="rosterEnabled"
            :event-id="event.id"
            @changed="stageHours(selectedAssignment, $event)"
            @close="selectedAssignment = null"
        />
        <UnsavedChangesDialog
            :dirty="unsaved"
            :busy="form.processing"
            @save="submit"
        />
        <Dialog
            v-if="!creating"
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
