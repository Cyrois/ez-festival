<script setup>
import {
    personalBreakDraft,
    personalBreakPayload,
    massPersonalBreaks,
    defaultSource,
} from '../../lib/personalBreaks';
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
import { computed, ref, watch, onUnmounted } from 'vue';
import { copiedShiftDraft, moveCopiedShift } from '../../lib/shiftCopy';
import { wallMinutes } from '../../lib/shiftBreaks';
import { trans } from 'laravel-vue-i18n';
import { draftRoster, assignmentOverlapUrl } from '../../lib/shiftAssignments';
import { scheduleReturnHref } from '../../lib/scheduleTimeline';

const props = defineProps({
    event: { type: Object, required: true },
    copying: { type: Boolean, default: false },
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
            color: props.prefill.color ?? 'teal',
            name: props.prefill.name ?? '',
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

const copied = props.copying ? copiedShiftDraft(props.prefill) : null;
const assignmentErrors = ref({});
const personalErrors = ref({});
const form = useForm({
    color: initialShift.value.color ?? 'teal',
    name: initialShift.value.name ?? '',
    location_id: initialShift.value.location_id,
    starts_at: initialShift.value.starts_at,
    ends_at: initialShift.value.ends_at,
    slots: copied?.slots ?? draftShiftSlots(initialShift.value.slots),
    breaks: copied?.breaks ?? draftShiftBreaks(initialShift.value.breaks),
    assignment_updates: [],
    assignment_removals: [],
    assignment_additions: copied?.assignment_additions ?? [],
    break_operations: [],
    ...props.returnContext,
});
const backHref = computed(() => scheduleReturnHref(props.returnContext));
const copyHref = computed(
    () =>
        `/team/shifts/${props.shift?.id}/copy?` +
        new URLSearchParams(props.returnContext).toString(),
);
const slotErrors = ref({});
const breakErrors = ref({});
const breakEditor = ref(null);
const breakRevision = ref(0);
const massBreakError = computed(
    () =>
        Object.entries(form.errors).find(
            ([key]) =>
                key === 'break_operations' ||
                key.startsWith('break_operations.'),
        )?.[1] ?? '',
);
const clearMassBreakErrors = () => {
    const keys = Object.keys(form.errors).filter(
        (key) =>
            key === 'break_operations' || key.startsWith('break_operations.'),
    );
    if (keys.length) form.clearErrors(...keys);
};
const clearSlotError = (key, field) => {
    if (slotErrors.value[key]) delete slotErrors.value[key][field];
};
const deleting = ref(false);
const deleteBusy = ref(false);
const confirmationCount = ref(0);
const selectedSlot = ref(null);
const headerAssignOpen = ref(false);
const draftPeople = ref(copied?.people ?? {});
const overlapPreviews = ref({});
let nextDraftId = -(form.assignment_additions.length + 1);
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
            breaks: form.breaks,
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
        validation_errors: assignmentErrors.value[row.id] ?? [],
    })),
}));
const detailsDirty = computed(
    () =>
        !creating.value &&
        ['name', 'location_id', 'starts_at', 'ends_at'].some(
            (key) => form[key] !== initialShift.value[key],
        ),
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
    delete assignmentErrors.value[assignment.id];
    if (assignment.id < 0) {
        form.assignment_additions = form.assignment_additions.filter(
            (row) => row._key !== assignment.id,
        );
        delete draftPeople.value[assignment.id];
        form.break_operations = form.break_operations
            .filter(
                (op) =>
                    op.type !== 'person' || op.assignment_key !== assignment.id,
            )
            .map((op) =>
                op.type === 'mass'
                    ? {
                          ...op,
                          assignment_keys: op.assignment_keys.filter(
                              (key) => key !== assignment.id,
                          ),
                      }
                    : op,
            )
            .filter((op) => op.type !== 'mass' || op.assignment_keys.length);
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
    const personHours =
        payload.hours_mode === 'full_shift'
            ? { starts_at: form.starts_at, ends_at: form.ends_at }
            : payload;
    const breaks = [];
    form.assignment_additions.push({ ...payload, breaks, _key: id });
    recordPerson(id, personHours, breaks);
};
const stageHours = (assignment, data, record = true) => {
    if (!rosterEnabled.value) return;
    previewRequests.get(assignment.id)?.abort();
    previewRequests.delete(assignment.id);
    delete assignmentErrors.value[assignment.id];
    delete personalErrors.value[assignment.id];
    const { overlaps, other_shifts, ...hours } = data;
    hours.breaks ??= personalBreakDraft(assignment.breaks ?? []);
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
    const resolved =
        hours.hours_mode === 'full_shift'
            ? { starts_at: form.starts_at, ends_at: form.ends_at }
            : hours;
    if (record) recordPerson(assignment.id, resolved, hours.breaks);
    if (overlaps)
        overlapPreviews.value[assignment.id] = {
            overlaps,
            ...(other_shifts ? { other_shifts } : {}),
        };
};
const stageBreaks = (assignment, breaks) => {
    const pending =
        assignment.id < 0
            ? form.assignment_additions.find(
                  (row) => row._key === assignment.id,
              )
            : form.assignment_updates.find((row) => row.id === assignment.id);
    const mode = pending?.hours_mode ?? 'custom';
    stageHours(assignment, {
        hours_mode: mode,
        ...(mode === 'custom'
            ? { starts_at: assignment.starts_at, ends_at: assignment.ends_at }
            : {}),
        breaks,
    });
};
const resizeHours = async (assignment, hours) => {
    stageHours(assignment, { hours_mode: 'custom', ...hours });
    await previewHours(assignment, {
        hours_mode: 'custom',
        starts_at: hours.starts_at,
        ends_at: hours.ends_at,
    });
};
const previewHours = async (assignment, hours) => {
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
            hours_mode: hours.hours_mode ?? 'custom',
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
const recordPerson = (key, hours, breaks) => {
    form.break_operations.push({
        type: 'person',
        assignment_key: key,
        starts_at: hours.starts_at,
        ends_at: hours.ends_at,
        breaks: personalBreakPayload(breaks),
    });
};
const bulkNotes = ref([]);
const addMassBreak = (row) => {
    if (!rosterEnabled.value) return;
    const people = rosterShift.value.assignments;
    const result = massPersonalBreaks(people, row);
    if (result.conflict) {
        showError(trans('team.scheduling.breaks.errors.mass_conflict'));
        return;
    }
    if (result.people.length) {
        form.break_operations.push({
            type: 'mass',
            assignment_keys: people.map((person) => person.id),
            break: {
                duration_minutes: Number(row.duration_minutes),
                starts_at: row.starts_at,
            },
        });
        for (const person of result.people)
            stageHours(
                person,
                {
                    hours_mode: 'custom',
                    starts_at: person.starts_at,
                    ends_at: person.ends_at,
                    breaks: person.breaks,
                },
                false,
            );
    }
    bulkNotes.value = result.skipped
        ? [
              trans('team.scheduling.breaks.skipped_outside', {
                  count: result.skipped,
              }),
          ]
        : [];
};
if (copied) {
    for (const row of form.breaks)
        form.break_operations.push({
            type: 'default',
            source: defaultSource(row),
            break: {
                duration_minutes: row.duration_minutes,
                starts_at: row.starts_at,
            },
            apply: false,
        });
    for (const row of form.assignment_additions)
        recordPerson(
            row._key,
            row.hours_mode === 'full_shift' ? form : row,
            row.breaks,
        );
}
const selectedBreakErrors = computed(
    () => personalErrors.value[selectedAssignment.value?.id] ?? {},
);
let previousCopyStart = form.starts_at;
watch(
    () => [form.starts_at, form.ends_at],
    () => {
        if (
            !props.copying ||
            !Number.isFinite(wallMinutes(form.starts_at)) ||
            !Number.isFinite(wallMinutes(form.ends_at)) ||
            form.starts_at >= form.ends_at
        )
            return;
        const moved = moveCopiedShift(form, previousCopyStart);
        for (const row of moved.breaks) {
            const before = form.breaks.find((old) => old._key === row._key);
            if (row.starts_at !== before.starts_at) {
                form.break_operations.push({
                    type: 'default',
                    source: defaultSource(row),
                    break: {
                        duration_minutes: row.duration_minutes,
                        starts_at: row.starts_at,
                    },
                    apply: false,
                });
            }
        }
        for (const row of moved.assignment_additions)
            recordPerson(
                row._key,
                row.hours_mode === 'full_shift' ? form : row,
                row.breaks,
            );
        form.assignment_additions = moved.assignment_additions;
        form.breaks = moved.breaks;
        previousCopyStart = form.starts_at;
        for (const assignment of rosterShift.value.assignments) {
            const row = form.assignment_additions.find(
                (row) => row._key === assignment.id,
            );
            if (row)
                previewHours(assignment, {
                    hours_mode: row.hours_mode,
                    ...(row.hours_mode === 'custom'
                        ? { starts_at: row.starts_at, ends_at: row.ends_at }
                        : {}),
                });
        }
    },
);
onUnmounted(() => previewRequests.forEach((controller) => controller.abort()));
const { showError, showFormError } = useFlashToast();

const canWrite = computed(() => props.canManage && !props.event.is_locked);
const displayName = computed(() =>
    creating.value
        ? trans(
              props.copying
                  ? 'team.scheduling.copy.title'
                  : 'team.scheduling.actions.new',
          )
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
    const submittedPeople = [...form.assignment_additions];
    const submittedUpdates = [...form.assignment_updates];
    form.transform((data) => ({
        ...data,
        slots: shiftSlotPayload(data.slots, true),
        breaks: shiftBreakPayload(data.breaks).map((row, i) => ({
            ...row,
            ...(data.breaks[i].id == null
                ? { client_key: data.breaks[i]._key }
                : {}),
        })),
        assignment_updates: data.assignment_updates.map((row) => ({
            ...row,
            ...(row.breaks ? { breaks: personalBreakPayload(row.breaks) } : {}),
        })),
        assignment_additions: data.assignment_additions.map(
            ({ _key, ...row }) => ({
                ...row,
                client_key: _key,
                ...(row.breaks
                    ? { breaks: personalBreakPayload(row.breaks) }
                    : {}),
            }),
        ),
    }));
    const options = {
        preserveScroll: true,
        onError: (errors) => {
            assignmentErrors.value = {};
            personalErrors.value = {};
            for (const [path, message] of Object.entries(errors)) {
                const match =
                    /^(assignment_additions|assignment_updates)\.(\d+)(?:\..*)?$/.exec(
                        path,
                    );
                const key =
                    match &&
                    (match[1] === 'assignment_additions'
                        ? submittedPeople[Number(match[2])]?._key
                        : submittedUpdates[Number(match[2])]?.id);
                if (key) {
                    assignmentErrors.value[key] ??= [];
                    assignmentErrors.value[key].push(message);
                    if (path.includes('.breaks.')) {
                        const submittedRow =
                            match[1] === 'assignment_additions'
                                ? submittedPeople[Number(match[2])]
                                : submittedUpdates[Number(match[2])];
                        const breakPath = path.replace(
                            `${match[1]}.${match[2]}.`,
                            '',
                        );
                        const mapped = shiftBreakErrors(
                            personalBreakDraft(submittedRow.breaks ?? []),
                            { [breakPath]: message },
                        );
                        personalErrors.value[key] ??= {};
                        for (const [breakKey, fields] of Object.entries(mapped))
                            personalErrors.value[key][breakKey] = {
                                ...(personalErrors.value[key][breakKey] ?? {}),
                                ...fields,
                            };
                    }
                }
            }
            const invalidPerson = Object.keys(personalErrors.value)[0];
            if (invalidPerson)
                selectedAssignment.value =
                    timelineShift.value.assignments.find(
                        (row) => String(row.id) === invalidPerson,
                    ) ?? null;
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
            form.break_operations = [];
            form.assignment_updates = [];
            form.assignment_removals = [];
            form.assignment_additions = [];
            draftPeople.value = {};
            overlapPreviews.value = {};
            assignmentErrors.value = {};
            personalErrors.value = {};
            bulkNotes.value = [];
            breakRevision.value++;
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
                        {{
                            $t(
                                copying
                                    ? 'team.scheduling.copy.unsaved'
                                    : 'team.scheduling.shift_lead',
                            )
                        }}
                    </p>
                </div>
                <div
                    v-if="canWrite && !creating"
                    class="flex shrink-0 flex-wrap items-center justify-end gap-2"
                >
                    <Button
                        :href="copyHref"
                        target="_blank"
                        rel="noopener noreferrer"
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
                    @move-break="stageBreaks($event.assignment, $event.breaks)"
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
                        :key="breakRevision"
                        :model-value="[]"
                        mass
                        :people="rosterShift.assignments"
                        :options="breakOptions"
                        :starts-at="form.starts_at"
                        :ends-at="form.ends_at"
                        :collection-error="massBreakError"
                        :editable="canWrite"
                        :busy="form.processing"
                        :disabled-reason="
                            $t(
                                event.is_locked
                                    ? 'team.scheduling.locked'
                                    : 'team.scheduling.no_permission',
                            )
                        "
                        @mass-add="addMassBreak"
                        @clear-error="clearMassBreakErrors"
                        @clear-containment-errors="clearMassBreakErrors"
                    />
                    <p
                        v-for="note in bulkNotes"
                        :key="note"
                        class="text-sm text-muted"
                        role="status"
                    >
                        {{ note }}
                    </p>
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
            :break-options="breakOptions"
            :break-errors="selectedBreakErrors"
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
