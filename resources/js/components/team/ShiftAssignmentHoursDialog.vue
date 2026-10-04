<script setup>
import { computed, ref, watch, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Dialog } from '../ui/dialog';
import { Input } from '../ui/input';
import { FormField } from '../ui/form-field';
import { Checkbox } from '../ui/checkbox';
import { Button } from '../ui/button';
import { useFlashToast } from '../../composables/useFlashToast';
import { validAssignmentHours } from '../../lib/shiftAssignments';
import ShiftOverlapWarnings from './ShiftOverlapWarnings.vue';

const props = defineProps({
    shift: { type: Object, required: true },
    assignment: { type: Object, required: true },
    eventId: { type: Number, required: true },
    enabled: { type: Boolean, default: false },
    returnContext: { type: Object, default: () => ({}) },
    deferred: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'busy', 'changed']);
const form = useForm({
    hours_mode:
        props.assignment.starts_at === props.shift.starts_at &&
        props.assignment.ends_at === props.shift.ends_at
            ? 'full_shift'
            : 'custom',
    starts_at: props.assignment.starts_at,
    ends_at: props.assignment.ends_at,
});
const { showFormError } = useFlashToast();
const fullShift = computed({
    get: () => form.hours_mode === 'full_shift',
    set: (checked) => {
        form.hours_mode = checked ? 'full_shift' : 'custom';
        if (checked) {
            form.starts_at = props.shift.starts_at;
            form.ends_at = props.shift.ends_at;
        }
    },
});
const hoursPayload = () => ({
    hours_mode: form.hours_mode,
    ...(fullShift.value
        ? {}
        : { starts_at: form.starts_at, ends_at: form.ends_at }),
});
const bounds = computed(() => ({
    from: props.shift.starts_at.replace('T', ' '),
    to: props.shift.ends_at.replace('T', ' '),
}));
const valid = computed(() =>
    validAssignmentHours(
        props.shift,
        form.hours_mode,
        form.starts_at,
        form.ends_at,
    ),
);
const overlaps = ref(props.assignment.overlaps);
const checking = ref(false);
const previewFailed = ref(false);
let controller;
let timer;
let requestNumber = 0;
const preview = async () => {
    clearTimeout(timer);
    controller?.abort();
    const number = ++requestNumber;
    overlaps.value = [];
    previewFailed.value = false;
    if (!valid.value) {
        checking.value = false;
        return;
    }
    checking.value = true;
    controller = new AbortController();
    try {
        const response = await fetch(
            `/team/shifts/${props.shift.id}/${props.assignment.id > 0 ? `assignments/${props.assignment.id}/overlaps` : 'assignment-overlaps'}?${new URLSearchParams({ ...hoursPayload(), shift_starts_at: props.shift.starts_at, shift_ends_at: props.shift.ends_at, ...(props.assignment.id < 0 ? { team_engagement_id: props.assignment.team_engagement_id } : {}) })}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        );
        if (!response.ok) throw new Error('preview');
        const result = await response.json();
        if (number === requestNumber) overlaps.value = result.data;
    } catch (error) {
        if (number === requestNumber && error.name !== 'AbortError')
            previewFailed.value = true;
    } finally {
        if (number === requestNumber) checking.value = false;
    }
};
watch(
    () => [form.hours_mode, form.starts_at, form.ends_at],
    () => {
        form.clearErrors();
        controller?.abort();
        ++requestNumber;
        clearTimeout(timer);
        overlaps.value = [];
        previewFailed.value = false;
        checking.value = valid.value;
        if (valid.value) timer = setTimeout(preview, 250);
    },
);
onUnmounted(() => {
    clearTimeout(timer);
    ++requestNumber;
    controller?.abort();
});
const save = () => {
    if (!props.enabled || !valid.value || form.processing) return;
    if (props.deferred) {
        emit('changed', { ...hoursPayload(), overlaps: overlaps.value });
        emit('close');
        return;
    }
    emit('busy', true);
    form.transform(() => ({ ...props.returnContext, ...hoursPayload() })).put(
        `/team/events/${props.eventId}/shifts/${props.shift.id}/assignments/${props.assignment.id}`,
        {
            preserveScroll: true,
            onError: (errors) => showFormError(errors),
            onSuccess: () => emit('close'),
            onFinish: () => emit('busy', false),
        },
    );
};
</script>

<template>
    <Dialog
        :open="true"
        role="dialog"
        focus-trap
        sectioned
        :title="
            $t('team.scheduling.assignments.edit_hours', {
                name: assignment.name,
            })
        "
        :confirm-label="
            $t(
                deferred
                    ? 'team.scheduling.assignments.apply_hours'
                    : 'actions.save',
            )
        "
        confirm-variant="primary"
        :confirm-disabled="!enabled || !valid"
        :busy="form.processing"
        @cancel="emit('close')"
        @confirm="save"
    >
        <div class="space-y-4">
            <p
                v-if="deferred"
                class="text-sm text-muted"
            >
                {{ $t('team.scheduling.assignments.draft_hint') }}
            </p>
            <Checkbox
                v-model="fullShift"
                :label="$t('team.scheduling.assignments.full_shift')"
                :disabled="form.processing"
            />
            <p
                v-if="fullShift"
                class="m-0 text-sm text-muted"
            >
                {{ bounds.from }}–{{ bounds.to }}
            </p>
            <div
                v-else
                class="grid gap-3 sm:grid-cols-2"
            >
                <FormField
                    :label="$t('team.scheduling.fields.start')"
                    :error="form.errors.starts_at"
                    required
                >
                    <template #default="{ id, invalid }"
                        ><Input
                            :id="id"
                            v-model="form.starts_at"
                            type="datetime-local"
                            :min="shift.starts_at"
                            :max="shift.ends_at"
                            :invalid="invalid"
                            :disabled="form.processing"
                    /></template>
                </FormField>
                <FormField
                    :label="$t('team.scheduling.fields.end')"
                    :error="form.errors.ends_at"
                    required
                >
                    <template #default="{ id, invalid }"
                        ><Input
                            :id="id"
                            v-model="form.ends_at"
                            type="datetime-local"
                            :min="shift.starts_at"
                            :max="shift.ends_at"
                            :invalid="invalid"
                            :disabled="form.processing"
                    /></template>
                </FormField>
            </div>
            <p class="m-0 text-xs text-muted">
                {{ $t('team.scheduling.assignments.hours_hint', bounds) }}
            </p>
            <p
                v-if="!valid"
                class="m-0 text-sm text-danger"
                role="alert"
            >
                {{ $t('team.scheduling.assignments.errors.hours', bounds) }}
            </p>
            <p
                v-if="fullShift && form.errors.ends_at"
                class="m-0 text-sm text-danger"
                role="alert"
            >
                {{ form.errors.ends_at }}
            </p>
            <div aria-live="polite">
                <p
                    v-if="checking"
                    class="m-0 text-sm text-muted"
                >
                    {{ $t('team.scheduling.assignments.preview_loading') }}
                </p>
                <template v-else-if="previewFailed">
                    <p class="m-0 text-sm text-warning">
                        {{ $t('team.scheduling.assignments.preview_failed') }}
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="form.processing"
                        @click="preview"
                        >{{
                            $t('team.scheduling.assignments.preview_retry')
                        }}</Button
                    >
                </template>
                <ShiftOverlapWarnings
                    v-else
                    :overlaps="overlaps"
                />
            </div>
        </div>
    </Dialog>
</template>
