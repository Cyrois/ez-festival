<script setup>
import { computed, ref, watch, onUnmounted, useId } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Badge } from '../ui/badge';
import { Avatar } from '../ui/avatar';
import { Tag } from '../ui/tag';
import { SegmentedControl } from '../ui/segmented-control';
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
    assignmentCandidatesUrl,
    validAssignmentHours,
    assignmentOverlapDetails,
    assignmentDurationLabel,
} from '../../lib/shiftAssignments';

const props = defineProps({
    shift: { type: Object, required: true },
    eventId: { type: Number, required: true },
    requirement: { type: Object, default: null },
    returnContext: { type: Object, default: () => ({}) },
    deferred: { type: Boolean, default: false },
    pendingMemberIds: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'assigned']);
const table = ref(null);
const overlapTooltipId = useId();
const targetSlot = computed(() => props.requirement);
const extra = computed(() => !props.requirement);

const selected = ref(null);
const search = ref('');
const roleFilter = ref(extra.value ? 'everyone' : 'has_role');
const roleOptions = computed(() => [
    { value: 'everyone', label: trans('team.scheduling.assignments.everyone') },
    { value: 'has_role', label: trans('team.scheduling.assignments.has_role') },
]);
const shiftTimes = computed(
    () =>
        `${props.shift.starts_at.slice(11, 16)}–${props.shift.ends_at.slice(11, 16)}`,
);
const chosenHours = computed(() => (fullShift.value ? props.shift : form));
const overlapDetails = (candidate) =>
    assignmentOverlapDetails(candidate.overlaps, chosenHours.value, trans);
const emptyMessage = computed(() =>
    search.value.trim()
        ? trans('team.scheduling.assignments.empty_search', {
              search: search.value.trim(),
          })
        : roleFilter.value === 'has_role'
          ? trans('team.scheduling.assignments.empty_role', {
                role: targetSlot.value?.role_name,
            })
          : trans('team.scheduling.assignments.empty_people'),
);
const pick = (candidate) => {
    if (!candidate.on_shift && !form.processing) selected.value = candidate;
};
let tableElement;
const styleRows = () => {
    tableElement?.querySelectorAll('tr[data-candidate-id]').forEach((row) => {
        const picked = String(selected.value?.id) === row.dataset.candidateId;
        row.classList.toggle('bg-primary-soft', picked);
        row.classList.toggle('hover:bg-primary-soft', picked);
        row.classList.toggle('[&>td:first-child]:border-l-2', picked);
        row.classList.toggle('[&>td:first-child]:border-l-primary', picked);
    });
};
watch(selected, styleRows);
const tableOptions = {
    serverSide: true,
    ordering: false,
    pageLength: 25,
    lengthChange: false,
    layout: {
        topStart: null,
        topEnd: null,
        bottomStart: null,
        bottomEnd: {
            paging: { firstLast: false, numbers: false, previousNext: true },
        },
    },
    createdRow: (row, candidate) => {
        row.dataset.candidateId = candidate.id;
        row.classList.add(
            ...(candidate.on_shift
                ? ['bg-page', 'opacity-50', 'cursor-not-allowed']
                : ['cursor-pointer']),
        );
        row.addEventListener('click', () => pick(candidate));
    },
    drawCallback: function () {
        const api = this.api();
        tableElement = api.table().node();
        api.table()
            .container()
            .querySelector('.dt-paging')
            ?.classList.toggle('hidden', api.page.info().pages <= 1);
        const empty = tableElement.querySelector('td.dt-empty');
        if (empty) empty.textContent = emptyMessage.value;
        styleRows();
    },
};
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
    invalidateCandidates();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => table.value?.search(search.value), 300);
});
const columns = computed(() => [
    {
        data: 'name',
        title: trans('team.scheduling.assignments.candidate_person'),
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
        targetSlot.value?.id ?? null,
        null,
        form.hours_mode,
        form.starts_at,
        form.ends_at,
    );
    delete query.team_engagement_id;
    if (extra.value) query.extra = '1';
    if (typeof targetSlot.value?.id === 'string') {
        delete query.shift_role_slot_id;
        query.role_id = targetSlot.value.role_id;
    }
    query.search = search.value.trim();
    query.page = Math.floor(data.start / data.length) + 1;
    query.per_page = data.length;
    if (!extra.value) query.role_filter = roleFilter.value;
    if (!props.shift.id) {
        query.shift_starts_at = props.shift.starts_at;
        query.shift_ends_at = props.shift.ends_at;
    }
    const fetchCandidates = async (params) => {
        const response = await fetch(
            `${assignmentCandidatesUrl(props.shift, props.eventId)}?${new URLSearchParams(params)}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        );
        const result = await response.json();
        if (!response.ok) {
            if (result.errors) showFormError(result.errors);
            throw new Error(trans('team.scheduling.assignments.search_failed'));
        }
        result.data = result.data.map((candidate) => ({
            ...candidate,
            on_shift:
                candidate.on_shift ||
                props.pendingMemberIds.includes(candidate.id),
        }));
        return result;
    };
    try {
        const result = await fetchCandidates(query);
        if (number !== requestNumber) return;
        if (selected.value) {
            const selectedId = selected.value.id;
            let refreshed = result.data.find(
                (candidate) => candidate.id === selectedId,
            );
            // Keep a selection across pages, but refresh its availability and
            // clear it if the active search/filter no longer includes it.
            if (!refreshed) {
                const selection = await fetchCandidates({
                    ...query,
                    selected_id: selectedId,
                    page: 1,
                    per_page: 1,
                });
                if (number !== requestNumber) return;
                refreshed = selection.data[0];
            }
            if (selected.value?.id === selectedId) {
                selected.value =
                    refreshed && !refreshed.on_shift ? refreshed : null;
            }
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
const invalidateCandidates = () => {
    ++requestNumber;
    controller?.abort();
    loading.value = false;
    loaded.value = false;
};
watch(roleFilter, () => {
    invalidateCandidates();
    table.value?.reload();
});
watch(
    () => [form.hours_mode, form.starts_at, form.ends_at],
    () => {
        form.clearErrors();
        invalidateCandidates();
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
    if (props.deferred) {
        const payload = assignmentPayload(
            targetSlot.value?.id ?? null,
            selected.value.id,
            form.hours_mode,
            form.starts_at,
            form.ends_at,
        );
        if (typeof targetSlot.value?.id === 'string') {
            delete payload.shift_role_slot_id;
            payload.slot_key = targetSlot.value.id;
        }
        emit('assigned', {
            candidate: selected.value,
            slot: targetSlot.value,
            ...payload,
        });
        emit('close');
        return;
    }
    form.transform(() => ({
        ...props.returnContext,
        ...assignmentPayload(
            targetSlot.value?.id ?? null,
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
            onSuccess: () => {
                emit('assigned');
                emit('close');
            },
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
            targetSlot
                ? $t('team.scheduling.assignments.dialog_title', {
                      role: targetSlot.role_name,
                  })
                : $t('team.scheduling.assignments.header_assign')
        "
        :confirm-label="
            selected
                ? $t('team.scheduling.assignments.assign_person', {
                      name: selected.name,
                  })
                : $t('team.scheduling.assignments.assign')
        "
        confirm-variant="primary"
        :confirm-disabled="
            !selected || selected.on_shift || !validHours || loading || !loaded
        "
        :busy="form.processing"
        @cancel="emit('close')"
        @confirm="assign"
    >
        <template #subtitle>
            {{
                targetSlot
                    ? $t('team.scheduling.assignments.dialog_subtitle', {
                          name: shift.name,
                          role: targetSlot.role_name,
                          times: shiftTimes,
                      })
                    : $t('team.scheduling.assignments.extra_subtitle', {
                          name: shift.name,
                          times: shiftTimes,
                      })
            }}
        </template>
        <template
            v-if="!selected"
            #footer-hint
        >
            {{ $t('team.scheduling.assignments.pick_person') }}
        </template>
        <div class="space-y-4">
            <p
                v-if="deferred"
                class="text-sm text-muted"
            >
                {{
                    $t('team.scheduling.assignments.draft_hint', {
                        action: $t(
                            shift.id
                                ? 'team.scheduling.actions.save'
                                : 'team.scheduling.actions.create',
                        ),
                    })
                }}
            </p>
            <p
                v-if="!validHours"
                class="text-sm text-danger"
                role="alert"
            >
                {{
                    $t('team.scheduling.assignments.errors.hours', {
                        from: shift.starts_at.replace('T', ' '),
                        to: shift.ends_at.replace('T', ' '),
                    })
                }}
            </p>
            <p
                v-for="field in [
                    'extra',
                    'shift_role_slot_id',
                    'team_engagement_id',
                ]"
                v-show="form.errors[field]"
                :key="field"
                class="text-sm text-danger"
                role="alert"
            >
                {{ form.errors[field] }}
            </p>
            <p
                v-if="candidateError"
                class="text-sm text-danger"
                role="alert"
            >
                {{ candidateError }}
            </p>
            <template v-if="targetSlot || extra">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="relative min-w-0 flex-1">
                        <Icon
                            :name="['fas', 'magnifying-glass']"
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            class="h-11 pl-9"
                            :placeholder="
                                $t('team.scheduling.assignments.search_people')
                            "
                            :aria-label="
                                $t('team.scheduling.assignments.search_people')
                            "
                            :disabled="form.processing"
                        />
                    </div>
                    <SegmentedControl
                        v-if="!extra"
                        v-model="roleFilter"
                        :options="roleOptions"
                        :aria-label="
                            $t('team.scheduling.assignments.role_filter')
                        "
                        :disabled="form.processing"
                        class="shrink-0"
                    />
                </div>
                <p
                    v-if="!extra"
                    class="text-xs text-muted"
                >
                    {{
                        $t(
                            roleFilter === 'has_role'
                                ? 'team.scheduling.assignments.has_role_hint'
                                : 'team.scheduling.assignments.everyone_hint',
                            { role: targetSlot.role_name },
                        )
                    }}
                </p>
                <div class="[&_.dt-layout-table>div]:overflow-visible!">
                    <DataTable
                        :key="targetSlot?.id ?? 'extra'"
                        ref="table"
                        :ajax="ajax"
                        :columns="columns"
                        :options="tableOptions"
                        class="[&_tbody_td]:py-2!"
                    >
                        <template #candidate="{ rowData }">
                            <div class="flex min-w-0 items-center gap-3">
                                <Radio
                                    :model-value="selected?.id ?? ''"
                                    :value="rowData.id"
                                    name="assignment-person"
                                    :aria-label="rowData.name"
                                    :disabled="
                                        rowData.on_shift || form.processing
                                    "
                                    @update:model-value="pick(rowData)"
                                />
                                <Avatar
                                    :name="rowData.name"
                                    size="sm"
                                />
                                <div class="min-w-0">
                                    <p
                                        :title="rowData.name"
                                        class="max-w-36 truncate font-semibold sm:max-w-64"
                                        :class="
                                            selected?.id === rowData.id
                                                ? 'text-primary'
                                                : 'text-charcoal'
                                        "
                                    >
                                        {{ rowData.name }}
                                    </p>
                                </div>
                            </div>
                        </template>
                        <template #role="{ rowData }">
                            <Tag
                                v-if="rowData.role_name"
                                :name="rowData.role_name"
                                color="soft_blue"
                            />
                            <span
                                v-else
                                class="text-muted"
                            >
                                {{ $t('team.scheduling.assignments.no_role') }}
                            </span>
                        </template>
                        <template #availability="{ rowData }">
                            <Badge
                                v-if="rowData.on_shift"
                                pill
                                class="gap-1"
                            >
                                <Icon :name="['fas', 'check']" />
                                {{
                                    trans(
                                        'team.scheduling.assignments.on_shift',
                                    )
                                }}
                            </Badge>
                            <span
                                v-else-if="rowData.overlaps.length"
                                class="group relative inline-flex"
                                :aria-label="overlapDetails(rowData)"
                                :aria-describedby="`${overlapTooltipId}-${rowData.id}`"
                                tabindex="0"
                            >
                                <Badge
                                    pill
                                    variant="warning"
                                    class="gap-1"
                                >
                                    <Icon
                                        :name="['fas', 'circle-exclamation']"
                                    />
                                    {{
                                        trans(
                                            'team.scheduling.assignments.overlap_chip',
                                            {
                                                name: rowData.overlaps[0]
                                                    .shift_name,
                                                length: assignmentDurationLabel(
                                                    rowData.overlaps[0]
                                                        .overlap_minutes,
                                                    trans,
                                                ),
                                            },
                                        )
                                    }}
                                </Badge>
                                <span
                                    :id="`${overlapTooltipId}-${rowData.id}`"
                                    role="tooltip"
                                    class="pointer-events-none absolute right-0 bottom-full z-50 mb-2 hidden w-56 rounded-lg bg-charcoal px-3 py-2 text-xs whitespace-pre-line text-white shadow-lg group-hover:block group-focus-visible:block"
                                >
                                    {{ overlapDetails(rowData) }}
                                </span>
                            </span>
                            <Badge
                                v-else
                                pill
                                variant="success"
                                class="gap-1"
                            >
                                <Icon
                                    :name="['fas', 'circle']"
                                    class="text-[6px]"
                                />
                                {{ trans('team.scheduling.assignments.free') }}
                            </Badge>
                        </template>
                    </DataTable>
                </div>
            </template>
            <div class="space-y-3 border-t border-line pt-4">
                <h3 class="m-0 text-sm font-bold">
                    {{ $t('team.scheduling.assignments.hours') }}
                </h3>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <Checkbox
                        v-model="fullShift"
                        :label="$t('team.scheduling.assignments.full_shift')"
                        :disabled="form.processing"
                    />
                    <p
                        v-if="fullShift"
                        class="text-sm"
                    >
                        <span class="font-semibold">{{ shiftTimes }}</span>
                        <span class="text-muted">{{
                            $t('team.scheduling.assignments.full_shift_suffix')
                        }}</span>
                    </p>
                </div>
                <template v-if="!fullShift">
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
                                    :disabled="form.processing"
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
                                    :disabled="form.processing"
                                />
                            </template>
                        </FormField>
                    </div>
                    <p class="text-xs text-muted">
                        {{
                            $t('team.scheduling.assignments.hours_hint', {
                                from: shift.starts_at.slice(11, 16),
                                to: shift.ends_at.slice(11, 16),
                            })
                        }}
                    </p>
                </template>
            </div>
            <div
                v-if="selected?.overlaps.length && validHours && loaded"
                class="space-y-1 rounded-lg border border-warning/20 bg-warning/10 px-3 py-2 text-sm text-warning"
                role="status"
            >
                <p
                    v-for="warning in selected.overlaps"
                    :key="warning.shift_id"
                    class="flex items-start gap-2"
                >
                    <Icon
                        :name="['fas', 'circle-exclamation']"
                        class="mt-0.5"
                    />
                    {{
                        $t('team.scheduling.assignments.overlap_note', {
                            name: warning.shift_name,
                            length: assignmentDurationLabel(
                                warning.overlap_minutes,
                                trans,
                            ),
                        })
                    }}
                </p>
            </div>
        </div>
    </Dialog>
</template>
