<script setup>
import ShiftBreaks from './ShiftBreaks.vue';
import { personalBreakDraft } from '../../lib/personalBreaks';
import { computed, ref, watch, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Dialog } from '../ui/dialog';
import { Input } from '../ui/input';
import { FormField } from '../ui/form-field';
import { Switch } from '../ui/switch';
import { Button } from '../ui/button';
import { Icon } from '../ui/icon';
import { Tooltip } from '../ui/tooltip';
import {
    validAssignmentHours,
    assignmentOverlapUrl,
} from '../../lib/shiftAssignments';
import ShiftOverlapWarnings from './ShiftOverlapWarnings.vue';

const props = defineProps({
    breakOptions: {
        type: Object,
        default: () => ({ durations: [], default_duration: null }),
    },
    breakErrors: { type: Object, default: () => ({}) },
    shift: { type: Object, required: true },
    assignment: { type: Object, required: true },
    enabled: { type: Boolean, default: false },
    supervisorError: { type: String, default: '' },
    eventId: { type: Number, default: null },
});
const emit = defineEmits(['close', 'changed']);
const form = useForm({
    hours_mode:
        props.assignment.starts_at === props.shift.starts_at &&
        props.assignment.ends_at === props.shift.ends_at
            ? 'full_shift'
            : 'custom',
    starts_at: props.assignment.starts_at,
    ends_at: props.assignment.ends_at,
});
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
const otherShifts = ref(props.assignment.other_shifts ?? []);
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
            `${assignmentOverlapUrl(props.shift, props.eventId, props.assignment)}?${new URLSearchParams({ ...hoursPayload(), shift_starts_at: props.shift.starts_at, shift_ends_at: props.shift.ends_at, ...(props.assignment.id < 0 ? { team_engagement_id: props.assignment.team_engagement_id } : {}) })}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        );
        if (!response.ok) throw new Error('preview');
        const result = await response.json();
        if (number === requestNumber) {
            overlaps.value = result.data;
            otherShifts.value = result.other_shifts ?? otherShifts.value;
        }
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
const personalBreaks = ref(personalBreakDraft(props.assignment.breaks ?? []));
const isSupervisor = ref(Boolean(props.assignment.is_supervisor));
const currentSupervisor = computed(() =>
    props.shift.assignments.find(
        (row) => row.is_supervisor && row.id !== props.assignment.id,
    ),
);
const save = () => {
    if (!props.enabled || !valid.value || form.processing) return;
    emit('changed', {
        ...hoursPayload(),
        is_supervisor: isSupervisor.value,
        breaks: personalBreaks.value,
        overlaps: overlaps.value,
        other_shifts: otherShifts.value,
    });
    emit('close');
};
</script>

<template>
    <Dialog
        :open="true"
        role="dialog"
        focus-trap
        sectioned
        class="max-w-2xl"
        :title="
            $t('team.scheduling.assignments.edit_hours', {
                name: assignment.name,
            })
        "
        :confirm-label="$t('team.scheduling.assignments.apply_hours')"
        confirm-variant="primary"
        :confirm-disabled="!enabled || !valid"
        :busy="form.processing"
        @cancel="emit('close')"
        @confirm="save"
    >
        <div class="space-y-6">
            <div class="space-y-4">
                <Switch
                    v-model="fullShift"
                    :label="$t('team.scheduling.assignments.full_shift')"
                    :disabled="form.processing"
                />
                <div class="grid gap-3 sm:grid-cols-2">
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
                                :disabled="fullShift || form.processing"
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
                                :disabled="fullShift || form.processing"
                        /></template>
                    </FormField>
                </div>
                <p
                    v-if="!valid"
                    class="m-0 text-sm text-danger"
                    role="alert"
                >
                    {{ $t('team.scheduling.assignments.errors.hours', bounds) }}
                </p>
            </div>
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <Switch
                        v-model="isSupervisor"
                        :label="$t('team.scheduling.supervisor.switch')"
                        :disabled="!enabled || form.processing"
                        aria-describedby="shift-supervisor-helper"
                    />
                    <Tooltip :label="$t('team.scheduling.supervisor.helper')">
                        <Icon
                            :name="['fas', 'circle-info']"
                            class="text-muted"
                            size="sm"
                        />
                        <template #content>
                            {{ $t('team.scheduling.supervisor.helper') }}
                        </template>
                    </Tooltip>
                </div>
                <p
                    id="shift-supervisor-helper"
                    class="sr-only"
                >
                    {{ $t('team.scheduling.supervisor.helper') }}
                </p>
                <p
                    v-if="isSupervisor && currentSupervisor"
                    class="text-xs text-muted"
                >
                    {{
                        $t('team.scheduling.supervisor.replace_hint', {
                            current: currentSupervisor.name,
                            next: assignment.name,
                        })
                    }}
                </p>
                <p
                    v-if="supervisorError"
                    class="text-sm text-danger"
                    role="alert"
                >
                    {{ supervisorError }}
                </p>
            </div>
            <div>
                <ShiftBreaks
                    v-model="personalBreaks"
                    :options="breakOptions"
                    :starts-at="fullShift ? shift.starts_at : form.starts_at"
                    :ends-at="fullShift ? shift.ends_at : form.ends_at"
                    :errors="breakErrors"
                    :editable="enabled"
                    personal
                />
            </div>
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
