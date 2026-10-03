<script setup>
import { computed, ref, watch, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Dialog } from '../ui/dialog';
import { Input } from '../ui/input';
import { FormField } from '../ui/form-field';
import { Checkbox } from '../ui/checkbox';
import { Radio } from '../ui/radio';
import { Icon } from '../ui/icon';
import { DataTable } from '../ui/data-table';
import { useFlashToast } from '../../composables/useFlashToast';
import {
    assignmentPayload,
    validAssignmentHours,
} from '../../lib/shiftAssignments';
import ShiftOverlapWarnings from './ShiftOverlapWarnings.vue';

const props = defineProps({
    shift: { type: Object, required: true },
    eventId: { type: Number, required: true },
    requirement: { type: Object, required: true },
    returnContext: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['close']);
const table = ref(null);
const selected = ref(null);
const search = ref('');
const form = useForm({
    hours_mode: 'full_shift',
    starts_at: props.shift.starts_at,
    ends_at: props.shift.ends_at,
});
const loading = ref(false);
const loaded = ref(false);
const candidateError = ref('');
const { showError, showFormError } = useFlashToast();
const validHours = computed(() =>
    validAssignmentHours(
        props.shift,
        form.hours_mode,
        form.starts_at,
        form.ends_at,
    ),
);
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
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => table.value?.search(search.value), 300);
});
const columns = computed(() => [
    {
        data: 'name',
        title: trans('team.scheduling.assignments.person'),
        render: { display: '#candidate' },
        orderable: false,
    },
    {
        data: 'role_name',
        title: trans('team.scheduling.slots.role'),
        render: { display: '#role' },
        orderable: false,
    },
    {
        data: 'group_name',
        title: trans('team.scheduling.assignments.group'),
        render: { display: '#group' },
        orderable: false,
    },
    {
        data: null,
        title: trans('team.scheduling.assignments.availability'),
        render: { display: '#availability' },
        orderable: false,
    },
]);
let controller;
let requestNumber = 0;
let refreshTimer;
let searchTimer;
const ajax = async (data, callback) => {
    controller?.abort();
    controller = new AbortController();
    const number = ++requestNumber;
    loading.value = true;
    loaded.value = false;
    candidateError.value = '';
    const empty = () =>
        callback({
            draw: data.draw,
            recordsTotal: 0,
            recordsFiltered: 0,
            data: [],
        });
    if (!validHours.value) {
        loading.value = false;
        empty();
        return;
    }
    const query = assignmentPayload(
        props.requirement.id,
        null,
        form.hours_mode,
        form.starts_at,
        form.ends_at,
    );
    delete query.team_engagement_id;
    query.search = data.search?.value || '';
    query.page = Math.floor(data.start / data.length) + 1;
    query.per_page = data.length;
    try {
        const response = await fetch(
            `/team/shifts/${props.shift.id}/assignment-candidates?${new URLSearchParams(query)}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        );
        const result = await response.json();
        if (number !== requestNumber) return;
        if (!response.ok) {
            if (result.errors) showFormError(result.errors);
            throw new Error(trans('team.scheduling.assignments.search_failed'));
        }
        if (selected.value) {
            const refreshed = result.data.find(
                (candidate) => candidate.id === selected.value.id,
            );
            if (refreshed) selected.value = refreshed;
        }
        loaded.value = true;
        callback({
            draw: data.draw,
            recordsTotal: result.meta.total,
            recordsFiltered: result.meta.total,
            data: result.data,
        });
    } catch (error) {
        if (error.name !== 'AbortError' && number === requestNumber) {
            candidateError.value = trans(
                'team.scheduling.assignments.search_failed',
            );
            showError(candidateError.value);
            empty();
        }
    } finally {
        if (number === requestNumber) loading.value = false;
    }
};
watch(
    () => [form.hours_mode, form.starts_at, form.ends_at],
    () => {
        form.clearErrors();
        ++requestNumber;
        controller?.abort();
        loading.value = false;
        loaded.value = false;
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(() => table.value?.reload(), 250);
    },
);
onUnmounted(() => {
    clearTimeout(searchTimer);
    clearTimeout(refreshTimer);
    ++requestNumber;
    controller?.abort();
});
const assign = () => {
    if (
        !selected.value ||
        selected.value.on_shift ||
        !validHours.value ||
        loading.value ||
        !loaded.value ||
        form.processing
    )
        return;
    form.transform(() => ({
        ...props.returnContext,
        ...assignmentPayload(
            props.requirement.id,
            selected.value.id,
            form.hours_mode,
            form.starts_at,
            form.ends_at,
        ),
    })).post(
        `/team/events/${props.eventId}/shifts/${props.shift.id}/assignments`,
        {
            preserveScroll: true,
            onError: (errors) => {
                showFormError(errors);
                table.value?.reload(false);
            },
            onSuccess: () => emit('close'),
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
        class="max-w-3xl"
        :title="
            $t('team.scheduling.assignments.dialog_title', {
                role: requirement.role_name,
            })
        "
        :confirm-label="$t('team.scheduling.assignments.assign')"
        confirm-variant="primary"
        :confirm-disabled="
            !selected || selected.on_shift || !validHours || loading || !loaded
        "
        :busy="form.processing"
        @cancel="emit('close')"
        @confirm="assign"
    >
        <div class="space-y-4">
            <p
                v-if="!validHours"
                class="text-sm text-danger"
                role="alert"
            >
                {{ $t('team.scheduling.assignments.errors.hours') }}
            </p>
            <p
                v-if="form.errors.shift_role_slot_id"
                class="text-sm text-danger"
                role="alert"
            >
                {{ form.errors.shift_role_slot_id }}
            </p>
            <p
                v-if="form.errors.team_engagement_id"
                class="text-sm text-danger"
                role="alert"
            >
                {{ form.errors.team_engagement_id }}
            </p>
            <p
                v-if="candidateError"
                class="text-sm text-danger"
                role="alert"
            >
                {{ candidateError }}
            </p>
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('team.scheduling.assignments.search_people')"
                :aria-label="$t('team.scheduling.assignments.search_people')"
            />
            <DataTable
                ref="table"
                :ajax="ajax"
                :columns="columns"
                :options="{
                    serverSide: true,
                    ordering: false,
                    pageLength: 5,
                    lengthChange: false,
                    layout: {
                        topStart: null,
                        topEnd: null,
                        bottomStart: null,
                        bottomEnd: {
                            paging: {
                                firstLast: false,
                                numbers: false,
                                previousNext: true,
                            },
                        },
                    },
                }"
            >
                <template #candidate="{ rowData }">
                    <Radio
                        :model-value="selected?.id ?? ''"
                        :value="rowData.id"
                        name="assignment-person"
                        :label="rowData.name"
                        :disabled="rowData.on_shift || form.processing"
                        @update:model-value="selected = rowData"
                    />
                </template>
                <template #role="{ rowData }">
                    {{ rowData.role_name || '—' }}
                </template>
                <template #group="{ rowData }">
                    {{ rowData.group_name || '—' }}
                </template>
                <template #availability="{ rowData }">
                    <span
                        v-if="rowData.on_shift"
                        class="text-xs text-muted"
                        >{{
                            trans('team.scheduling.assignments.on_shift')
                        }}</span
                    >
                    <span
                        v-else-if="rowData.overlaps.length"
                        class="inline-flex items-center gap-2 text-sm text-warning"
                    >
                        <Icon :name="['fas', 'circle-exclamation']" />
                        {{ trans('team.scheduling.assignments.not_available') }}
                    </span>
                    <span
                        v-else
                        class="text-sm text-success"
                        >{{ trans('team.scheduling.assignments.free') }}</span
                    >
                    <ShiftOverlapWarnings :overlaps="rowData.overlaps" />
                </template>
            </DataTable>
            <div class="space-y-3 border-t border-line pt-4">
                <h3 class="m-0 text-sm font-bold">
                    {{ $t('team.scheduling.assignments.hours') }}
                </h3>
                <Checkbox
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
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.starts_at"
                                type="datetime-local"
                                :min="shift.starts_at"
                                :max="shift.ends_at"
                                :invalid="invalid"
                                :disabled="fullShift || form.processing"
                            />
                        </template>
                    </FormField>
                    <FormField
                        :label="$t('team.scheduling.fields.end')"
                        :error="form.errors.ends_at"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.ends_at"
                                type="datetime-local"
                                :min="shift.starts_at"
                                :max="shift.ends_at"
                                :invalid="invalid"
                                :disabled="fullShift || form.processing"
                            />
                        </template>
                    </FormField>
                </div>
            </div>
        </div>
    </Dialog>
</template>
