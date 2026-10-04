<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { CardTitle } from '../ui/card';
import { Avatar } from '../ui/avatar';
import { Badge } from '../ui/badge';
import { Icon } from '../ui/icon';
import { IconButton } from '../ui/icon-button';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../ui/table';
import { labelTokens, fallbackLabelToken } from '../../lib/labelTokens';
import {
    scheduleRosterRows,
    resizedAssignment,
} from '../../lib/shiftAssignments';
import {
    timelineMinute,
    timelineIntersection,
    shiftTimelineTicks,
    shiftTimelineGrid,
    OPEN_ROLE_PATTERN,
    shiftOverlapIntervals,
    shiftHoursLabel,
    SCHEDULE_CELL_WIDTH,
    SCHEDULE_SLOT_MINUTES,
} from '../../lib/scheduleTimeline';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    shift: { type: Object, required: true },
    enabled: { type: Boolean, default: false },
    assignEnabled: { type: Boolean, default: undefined },
    canManage: { type: Boolean, default: false },
    disabledReason: { type: String, default: '' },
});
const emit = defineEmits(['assign', 'edit', 'remove', 'resize']);
const dragging = ref(null);
const canAssign = computed(() => props.assignEnabled ?? props.enabled);
const grid = computed(() => shiftTimelineGrid(bounds.value));
const totalTime = (assignment) => {
    const minutes =
        timelineMinute(assignment.ends_at) -
        timelineMinute(assignment.starts_at);
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return trans(
        hours && rest
            ? 'team.scheduling.roster.duration_both'
            : hours
              ? 'team.scheduling.roster.duration_hours'
              : 'team.scheduling.roster.duration_minutes',
        { hours, minutes: rest },
    );
};
const root = ref(null);
const width = ref(0);
let observer;
onMounted(() => {
    width.value = root.value?.clientWidth ?? 0;
    if (typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(([entry]) => {
            width.value = entry.contentRect.width;
        });
        observer.observe(root.value);
    }
});
onUnmounted(() => {
    observer?.disconnect();
    stopDrag();
});
const rows = computed(() =>
    scheduleRosterRows({
        ...props.shift,
        assignments: props.shift.assignments.map((row) =>
            dragging.value?.assignment.id === row.id
                ? { ...row, ...dragging.value.hours }
                : row,
        ),
    }),
);
const timelinePadding = 30;
const bounds = computed(() => ({
    starts_at: new Date(
        (timelineMinute(props.shift.starts_at) - timelinePadding) * 60000,
    )
        .toISOString()
        .slice(0, 16),
    ends_at: new Date(
        (timelineMinute(props.shift.ends_at) + timelinePadding) * 60000,
    )
        .toISOString()
        .slice(0, 16),
}));
const ticks = computed(() => shiftTimelineTicks(bounds.value));
const duration = computed(
    () =>
        timelineMinute(bounds.value.ends_at) -
        timelineMinute(bounds.value.starts_at),
);
const personWidth = computed(() =>
    width.value && width.value < 600 ? 144 : 240,
);
const timelineWidth = computed(() =>
    Math.max(
        (duration.value / SCHEDULE_SLOT_MINUTES) * SCHEDULE_CELL_WIDTH,
        width.value - personWidth.value - (props.canManage ? 88 : 0) - 4,
    ),
);
const tokens = computed(
    () => labelTokens[props.shift.color] ?? labelTokens[fallbackLabelToken],
);
const geometry = (interval) => ({
    '--bar-start': `${((interval.start + timelinePadding) / duration.value) * 100}%`,
    '--bar-width': `${((interval.end - interval.start) / duration.value) * 100}%`,
});
const shiftInterval = computed(() =>
    timelineIntersection(props.shift, props.shift),
);
const personInterval = (assignment) =>
    timelineIntersection(assignment, props.shift);
const shortBar = (assignment) => {
    const interval = personInterval(assignment);
    return (
        interval &&
        ((interval.end - interval.start) / duration.value) *
            timelineWidth.value <
            assignment.name.length * 6 + 92
    );
};
const warnings = (assignment) =>
    [...assignment.overlaps].sort(
        (a, b) =>
            a.starts_at.localeCompare(b.starts_at) || a.shift_id - b.shift_id,
    );
const warningLabel = (warning) =>
    trans('team.scheduling.roster.overlap', {
        name: warning.shift_name || trans('team.scheduling.unnamed_shift'),
        minutes: warning.overlap_minutes,
    });
const warningDetails = (assignment) =>
    warnings(assignment)
        .map(
            (warning) =>
                `${warningLabel(warning)} (${shiftHoursLabel(warning)})`,
        )
        .join('\n');
const assignmentTitle = (assignment) =>
    `${assignment.name} · ${shiftHoursLabel(assignment)}${assignment.overlaps.length ? '\n' + warningDetails(assignment) : ''}`;

const stopDrag = () => {
    window.removeEventListener('pointermove', moveDrag);
    window.removeEventListener('pointerup', finishDrag);
    window.removeEventListener('pointercancel', cancelDrag);
    window.removeEventListener('keydown', escapeDrag);
};
const cancelDrag = () => {
    dragging.value = null;
    stopDrag();
};
const escapeDrag = (event) => {
    if (event.key === 'Escape') cancelDrag();
};
const beginDrag = (event, assignment, edge) => {
    if (!props.enabled || !props.canManage || event.button !== 0) return;
    event.preventDefault();
    const canvas =
        event.currentTarget.closest('[data-person-bar]').parentElement;
    dragging.value = {
        assignment,
        edge,
        x: event.clientX,
        width: canvas.getBoundingClientRect().width,
        hours: { starts_at: assignment.starts_at, ends_at: assignment.ends_at },
    };
    event.currentTarget.setPointerCapture?.(event.pointerId);
    window.addEventListener('pointermove', moveDrag);
    window.addEventListener('pointerup', finishDrag);
    window.addEventListener('pointercancel', cancelDrag);
    window.addEventListener('keydown', escapeDrag);
};
const moveDrag = (event) => {
    const drag = dragging.value;
    if (!drag) return;
    const minute =
        timelineMinute(
            drag.edge === 'start'
                ? drag.assignment.starts_at
                : drag.assignment.ends_at,
        ) +
        ((event.clientX - drag.x) / drag.width) * duration.value;
    const hours = resizedAssignment(
        props.shift,
        drag.assignment,
        drag.edge,
        minute,
    );
    if (hours) drag.hours = hours;
};
const finishDrag = (event) => {
    if (!dragging.value) return;
    moveDrag(event);
    const { assignment, hours } = dragging.value;
    cancelDrag();
    if (
        props.enabled &&
        (assignment.starts_at !== hours.starts_at ||
            assignment.ends_at !== hours.ends_at)
    )
        emit('resize', { assignment, hours });
};
const resizeKey = (event, assignment, edge) => {
    if (!props.enabled || !props.canManage) return;
    const delta =
        event.key === 'ArrowLeft'
            ? -15
            : event.key === 'ArrowRight'
              ? 15
              : null;
    if (delta === null) return;
    event.preventDefault();
    const hours = resizedAssignment(
        props.shift,
        assignment,
        edge,
        timelineMinute(
            edge === 'start' ? assignment.starts_at : assignment.ends_at,
        ) + delta,
    );
    if (hours) emit('resize', { assignment, hours });
};
</script>

<template>
    <section
        ref="root"
        class="min-w-0"
    >
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <CardTitle>
                    {{ $t('team.scheduling.assignments.roster') }}
                </CardTitle>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{
                        $t('team.scheduling.grid.filled', {
                            filled: shift.filled_count,
                            needed: shift.total_needs,
                        })
                    }}
                </p>
            </div>
        </div>
        <p
            v-if="disabledReason"
            class="mb-3 text-sm text-muted"
            role="status"
        >
            {{ disabledReason }}
        </p>
        <Table
            :scroll-label="$t('team.scheduling.roster.scroll')"
            :style="{
                '--timeline-width': `${timelineWidth}px`,
                '--person-width': `${personWidth}px`,
            }"
        >
            <TableHeader>
                <TableRow variant="header">
                    <TableHead
                        class="sticky left-0 z-20 min-w-[var(--person-width)] border-r border-line px-3"
                        >{{ $t('team.scheduling.roster.person') }}</TableHead
                    >
                    <TableHead class="p-0 normal-case">
                        <div
                            class="relative h-10 w-[var(--timeline-width)]"
                            :aria-label="shiftHoursLabel(bounds)"
                        >
                            <div
                                class="pointer-events-none absolute inset-y-0 left-[var(--bar-start)] w-[var(--bar-width)] border-x opacity-50"
                                :class="tokens.solid"
                                :style="geometry(shiftInterval)"
                                aria-hidden="true"
                            />
                            <div
                                v-for="tick in grid"
                                :key="`grid-${tick.minute}`"
                                class="pointer-events-none absolute inset-y-0 left-[var(--tick-left)] border-l border-line/60"
                                :style="{ '--tick-left': `${tick.position}%` }"
                                aria-hidden="true"
                                data-roster-grid
                            />
                            <div
                                v-for="(tick, index) in ticks"
                                :key="tick.minute"
                                class="absolute inset-y-0 left-[var(--tick-left)] border-l border-line/60"
                                :style="{ '--tick-left': `${tick.position}%` }"
                            >
                                <span
                                    v-if="
                                        tick.showLabel &&
                                        (index === 0 ||
                                            index === ticks.length - 1 ||
                                            (Math.min(
                                                tick.minute,
                                                duration - tick.minute,
                                            ) /
                                                duration) *
                                                timelineWidth >=
                                                64)
                                    "
                                    class="absolute top-1/2 -translate-y-1/2 text-xs font-normal whitespace-nowrap"
                                    :class="[
                                        tick.minute >= timelinePadding &&
                                        tick.minute <=
                                            duration - timelinePadding
                                            ? 'text-charcoal'
                                            : '',
                                        index === 0 ||
                                        tick.minute === timelinePadding
                                            ? 'left-1'
                                            : index === ticks.length - 1 ||
                                                tick.minute ===
                                                    duration - timelinePadding
                                              ? 'right-1'
                                              : 'left-0 -translate-x-1/2',
                                    ]"
                                    >{{ tick.label
                                    }}<span
                                        v-if="tick.date"
                                        class="block text-[10px]"
                                        >{{ tick.date }}</span
                                    ></span
                                >
                            </div>
                        </div>
                    </TableHead>
                    <TableHead
                        v-if="canManage"
                        class="sticky right-0 z-20 min-w-22 border-l border-line px-2 text-center"
                        data-roster-actions
                        >{{ $t('team.scheduling.roster.actions') }}</TableHead
                    >
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow
                    v-for="row in rows"
                    :key="row.key"
                    :data-roster-row="row.key"
                    :class="
                        row.assignment?.overlaps.length
                            ? 'bg-warning/10 hover:bg-warning/10'
                            : !row.assignment
                              ? 'hover:bg-transparent'
                              : ''
                    "
                >
                    <TableCell
                        class="sticky left-0 z-10 border-r border-line bg-ground px-3 py-2"
                    >
                        <div
                            class="flex w-[calc(var(--person-width)-1.5rem)] items-center gap-2 rounded-lg"
                            :class="
                                row.assignment?.overlaps.length
                                    ? 'bg-warning/10'
                                    : ''
                            "
                        >
                            <Avatar
                                v-if="row.assignment"
                                :name="row.assignment.name"
                                size="sm"
                            />
                            <span
                                v-else
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-page text-muted"
                                aria-hidden="true"
                                ><Icon :name="['fas', 'user']"
                            /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="truncate text-sm font-semibold"
                                        :title="row.assignment?.name"
                                        >{{
                                            row.assignment?.name ??
                                            $t('team.scheduling.roster.open')
                                        }}</span
                                    >
                                    <Badge
                                        v-if="row.assignment?.is_extra"
                                        pill
                                        variant="warning"
                                        >{{
                                            $t(
                                                'team.scheduling.assignments.extra',
                                            )
                                        }}</Badge
                                    >
                                </div>
                                <p
                                    class="mt-1 mb-0 truncate text-xs text-muted"
                                    :title="
                                        row.assignment?.role_name ??
                                        row.slot.role_name
                                    "
                                >
                                    {{
                                        row.assignment?.role_name ??
                                        row.slot.role_name
                                    }}
                                </p>
                            </div>
                        </div>
                    </TableCell>
                    <TableCell class="p-0">
                        <div
                            class="relative isolate h-14 w-[var(--timeline-width)]"
                        >
                            <div
                                class="pointer-events-none absolute inset-0"
                                aria-hidden="true"
                            >
                                <div
                                    v-for="tick in grid"
                                    :key="tick.minute"
                                    data-roster-grid
                                    class="absolute inset-y-0 left-[var(--tick-left)] border-l border-line/60"
                                    :style="{
                                        '--tick-left': `${tick.position}%`,
                                    }"
                                />
                            </div>
                            <template
                                v-if="
                                    row.assignment &&
                                    personInterval(row.assignment)
                                "
                            >
                                <div
                                    class="absolute top-3 left-[var(--bar-start)] h-7 w-[var(--bar-width)] rounded-lg border"
                                    :class="
                                        row.assignment.overlaps.length
                                            ? 'border-warning/30 bg-warning/20'
                                            : tokens.classes
                                    "
                                    :style="
                                        geometry(personInterval(row.assignment))
                                    "
                                    :title="assignmentTitle(row.assignment)"
                                    data-person-bar
                                >
                                    <span
                                        v-if="!shortBar(row.assignment)"
                                        class="relative z-10 block truncate px-4 py-1 pr-20 text-xs font-semibold"
                                        >{{ row.assignment.name }}</span
                                    >
                                    <span
                                        class="absolute inset-y-0 right-4 z-10 flex items-center text-xs font-semibold whitespace-nowrap"
                                        data-assignment-duration
                                        >{{ totalTime(row.assignment) }}</span
                                    >
                                    <template v-if="canManage">
                                        <button
                                            v-for="edge in ['start', 'end']"
                                            :key="edge"
                                            type="button"
                                            role="slider"
                                            class="absolute inset-y-0 z-20 flex w-3 cursor-ew-resize touch-none items-center justify-center rounded-sm focus-visible:outline-2 focus-visible:outline-primary"
                                            :class="
                                                edge === 'start'
                                                    ? 'left-0'
                                                    : 'right-0'
                                            "
                                            :disabled="!enabled"
                                            :aria-label="
                                                $t(
                                                    `team.scheduling.roster.resize_${edge}`,
                                                    {
                                                        name: row.assignment
                                                            .name,
                                                    },
                                                )
                                            "
                                            :aria-valuemin="
                                                timelineMinute(shift.starts_at)
                                            "
                                            :aria-valuemax="
                                                timelineMinute(shift.ends_at)
                                            "
                                            :aria-valuenow="
                                                timelineMinute(
                                                    edge === 'start'
                                                        ? row.assignment
                                                              .starts_at
                                                        : row.assignment
                                                              .ends_at,
                                                )
                                            "
                                            :aria-valuetext="
                                                edge === 'start'
                                                    ? row.assignment.starts_at.replace(
                                                          'T',
                                                          ' ',
                                                      )
                                                    : row.assignment.ends_at.replace(
                                                          'T',
                                                          ' ',
                                                      )
                                            "
                                            :title="
                                                $t(
                                                    'team.scheduling.roster.resize_hint',
                                                )
                                            "
                                            @pointerdown="
                                                beginDrag(
                                                    $event,
                                                    row.assignment,
                                                    edge,
                                                )
                                            "
                                            @keydown="
                                                resizeKey(
                                                    $event,
                                                    row.assignment,
                                                    edge,
                                                )
                                            "
                                        >
                                            <Icon
                                                :name="['fas', 'grip-lines']"
                                                class="rotate-90 opacity-60"
                                                size="xs"
                                            />
                                        </button>
                                    </template>
                                </div>
                                <div
                                    v-for="(
                                        overlap, index
                                    ) in shiftOverlapIntervals(
                                        row.assignment,
                                        shift,
                                    )"
                                    :key="index"
                                    class="pointer-events-none absolute top-3 left-[var(--bar-start)] h-7 w-[var(--bar-width)] rounded-lg bg-[repeating-linear-gradient(135deg,transparent,transparent_4px,currentColor_4px,currentColor_6px)] text-warning opacity-40"
                                    :style="geometry(overlap)"
                                    aria-hidden="true"
                                    data-overlap-hatch
                                />
                                <span
                                    v-if="shortBar(row.assignment)"
                                    class="absolute top-0 left-[var(--bar-start)] z-10 max-w-64 truncate text-xs font-semibold"
                                    :style="
                                        geometry(personInterval(row.assignment))
                                    "
                                    :title="row.assignment.name"
                                    data-short-label
                                    >{{ row.assignment.name }}</span
                                >
                                <span
                                    v-if="row.assignment.overlaps.length"
                                    class="absolute top-10 left-[var(--bar-start)] z-10 w-[var(--bar-width)] truncate px-2 text-right text-xs text-warning"
                                    :style="
                                        geometry(personInterval(row.assignment))
                                    "
                                    :title="warningDetails(row.assignment)"
                                    tabindex="0"
                                    >{{
                                        warningLabel(
                                            warnings(row.assignment)[0],
                                        )
                                    }}</span
                                >
                                <span class="sr-only">{{
                                    assignmentTitle(row.assignment)
                                }}</span>
                                <span
                                    v-if="row.assignment.preview_status"
                                    class="absolute top-10 left-2 text-xs text-warning"
                                    role="status"
                                    >{{
                                        $t(
                                            row.assignment.preview_status ===
                                                'loading'
                                                ? 'team.scheduling.assignments.preview_loading'
                                                : 'team.scheduling.assignments.preview_failed',
                                        )
                                    }}</span
                                >
                            </template>
                            <p
                                v-if="
                                    row.assignment &&
                                    !personInterval(row.assignment)
                                "
                                class="absolute inset-x-2 top-5 text-xs text-danger"
                                role="alert"
                            >
                                {{
                                    $t('team.scheduling.roster.outside_bounds')
                                }}
                            </p>
                            <div
                                v-if="!row.assignment"
                                class="absolute top-3 left-[var(--bar-start)] flex h-8 w-[var(--bar-width)] items-center rounded-lg border border-dashed px-2 text-xs font-semibold text-charcoal"
                                :class="OPEN_ROLE_PATTERN"
                                :style="geometry(shiftInterval)"
                                data-open-bar
                            >
                                <span class="min-w-0 truncate">{{
                                    $t('team.scheduling.roster.open_role', {
                                        role: row.slot.role_name,
                                    })
                                }}</span>
                                <span
                                    class="ml-auto shrink-0 pl-2 text-muted"
                                    >{{ totalTime(shift) }}</span
                                >
                            </div>
                        </div>
                    </TableCell>
                    <TableCell
                        v-if="canManage"
                        class="sticky right-0 z-10 border-l border-line bg-ground px-2 py-2"
                    >
                        <span
                            v-if="row.assignment"
                            class="flex justify-center gap-1"
                            :title="disabledReason"
                            :tabindex="
                                !canAssign && disabledReason ? 0 : undefined
                            "
                            :aria-label="
                                !canAssign ? disabledReason : undefined
                            "
                        >
                            <IconButton
                                :icon="['fas', 'pencil']"
                                tone="edit"
                                :label="
                                    $t(
                                        'team.scheduling.assignments.edit_hours',
                                        { name: row.assignment.name },
                                    )
                                "
                                :disabled="!enabled"
                                @click="
                                    enabled && $emit('edit', row.assignment)
                                "
                            />
                            <IconButton
                                :icon="['fas', 'trash-can']"
                                tone="delete"
                                :label="
                                    $t(
                                        'team.scheduling.assignments.remove_person',
                                        { name: row.assignment.name },
                                    )
                                "
                                :disabled="!enabled"
                                @click="
                                    enabled && $emit('remove', row.assignment)
                                "
                            />
                        </span>
                        <span
                            v-else
                            class="flex justify-center"
                            :title="disabledReason"
                        >
                            <IconButton
                                :icon="['fas', 'plus']"
                                tone="edit"
                                :label="
                                    $t(
                                        'team.scheduling.assignments.assign_role',
                                        { role: row.slot.role_name },
                                    )
                                "
                                :disabled="!canAssign"
                                data-open-assign
                                @click="canAssign && $emit('assign', row.slot)"
                            />
                        </span>
                    </TableCell>
                </TableRow>
                <TableRow v-if="!rows.length"
                    ><TableCell
                        :colspan="canManage ? 3 : 2"
                        class="text-muted"
                        >{{
                            $t('team.scheduling.roster.empty_shift')
                        }}</TableCell
                    ></TableRow
                >
            </TableBody>
        </Table>
        <div
            v-if="rows.length"
            class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted"
        >
            <span class="flex items-center gap-1.5"
                ><span
                    class="h-2.5 w-4 rounded-sm border"
                    :class="tokens.classes"
                    aria-hidden="true"
                />{{ $t('team.scheduling.roster.legend_hours') }}</span
            >
            <span class="flex items-center gap-1.5"
                ><span
                    class="h-2.5 w-4 rounded-sm border border-dashed"
                    :class="OPEN_ROLE_PATTERN"
                    aria-hidden="true"
                />{{ $t('team.scheduling.roster.legend_open') }}</span
            >
            <span class="flex items-center gap-1.5"
                ><span
                    class="h-2.5 w-4 rounded-sm bg-warning/20"
                    aria-hidden="true"
                />{{ $t('team.scheduling.roster.legend_overlap') }}</span
            >
        </div>
        <div
            v-if="$slots['footer-actions']"
            class="mt-3 flex justify-end"
        >
            <slot name="footer-actions" />
        </div>
    </section>
</template>
