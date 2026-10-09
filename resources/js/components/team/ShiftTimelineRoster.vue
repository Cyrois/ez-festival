<script setup>
import { computed, onMounted, onUnmounted, ref, useId } from 'vue';
import { CardTitle } from '../ui/card';
import { Avatar } from '../ui/avatar';
import { Badge } from '../ui/badge';
import { Tooltip } from '../ui/tooltip';
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
    translatedAssignment,
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
import { wallMinutes } from '../../lib/shiftBreaks';
import { mealsForAssignment } from '../../lib/shiftMeals';
import { translatedPersonalBreak } from '../../lib/personalBreaks';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    shift: { type: Object, required: true },
    enabled: { type: Boolean, default: false },
    assignEnabled: { type: Boolean, default: undefined },
    canManage: { type: Boolean, default: false },
    disabledReason: { type: String, default: '' },
});
const emit = defineEmits(['assign', 'edit', 'remove', 'resize', 'move-break']);
const dragging = ref(null);
const warningTooltipId = useId();
const isDraggingBreak = (assignment, index) =>
    dragging.value?.edge === 'break' &&
    dragging.value.assignment.id === assignment.id &&
    dragging.value.breakIndex === index;
const breakPattern =
    'bg-page bg-[repeating-linear-gradient(135deg,transparent,transparent_4px,var(--color-line)_4px,var(--color-line)_8px)] text-muted';
const hasBreaks = computed(() =>
    props.shift.assignments.some((person) => person.breaks?.length),
);
const breakLabel = (row) =>
    `${row.starts_at.slice(11, 16)} · ${row.duration_minutes === 60 ? trans('team.scheduling.breaks.hour') : trans('team.scheduling.breaks.minutes', { minutes: row.duration_minutes })}`;
const breakIntervals = (person) =>
    (person.breaks ?? []).flatMap((row, breakIndex) => {
        const start = wallMinutes(row.starts_at);
        if (
            !Number.isFinite(start) ||
            !Number.isFinite(Number(row.duration_minutes))
        )
            return [];
        const range = {
            starts_at: row.starts_at,
            ends_at: new Date((start + Number(row.duration_minutes)) * 60000)
                .toISOString()
                .slice(0, 16),
        };
        const hit = timelineIntersection(range, person);
        if (!hit) return [];
        const origin = timelineMinute(person.starts_at);
        const interval = timelineIntersection(
            {
                starts_at: new Date((origin + hit.start) * 60000)
                    .toISOString()
                    .slice(0, 16),
                ends_at: new Date((origin + hit.end) * 60000)
                    .toISOString()
                    .slice(0, 16),
            },
            props.shift,
        );
        return interval
            ? [
                  {
                      ...interval,
                      breakIndex,
                      starts_at: row.starts_at,
                      duration_minutes: Number(row.duration_minutes),
                      atStart: hit.start === 0,
                      atEnd:
                          hit.end === timelineMinute(person.ends_at) - origin,
                      title: `${breakLabel(row)} · ${trans('team.scheduling.roster.legend_break')}`,
                  },
              ]
            : [];
    });

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
const fitsShiftName = (interval, name) => {
    return (
        interval &&
        ((interval.end - interval.start) / duration.value) *
            timelineWidth.value >=
            name.length * 6 + 92
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
const otherShiftTokens = (other) =>
    labelTokens[other.color] ?? labelTokens[fallbackLabelToken];
const otherShifts = (assignment) =>
    (assignment?.other_shifts ?? [])
        .map((other) => {
            const interval = timelineIntersection(other, bounds.value);
            return {
                ...other,
                interval: interval
                    ? {
                          start: interval.start - timelinePadding,
                          end: interval.end - timelinePadding,
                      }
                    : null,
            };
        })
        .filter((other) => other.interval);

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
const beginDrag = (event, assignment, edge, breakIndex = null) => {
    if (!props.enabled || !props.canManage || event.button !== 0) return;
    event.preventDefault();
    const canvas = event.currentTarget.closest('[data-roster-canvas]');
    dragging.value = {
        assignment,
        edge,
        breakIndex,
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
    if (drag.edge === 'break') {
        if (event.clientX === drag.x) {
            drag.hours = {
                starts_at: drag.assignment.starts_at,
                ends_at: drag.assignment.ends_at,
            };
            return;
        }
        const moved = translatedPersonalBreak(
            drag.assignment,
            drag.breakIndex,
            wallMinutes(drag.assignment.breaks[drag.breakIndex].starts_at) +
                ((event.clientX - drag.x) / drag.width) * duration.value,
        );
        if (moved)
            drag.hours = {
                starts_at: drag.assignment.starts_at,
                ends_at: drag.assignment.ends_at,
                breaks: drag.assignment.breaks.map((row, index) =>
                    index === drag.breakIndex ? moved : row,
                ),
            };
        return;
    }
    const minute =
        timelineMinute(
            drag.edge === 'end'
                ? drag.assignment.ends_at
                : drag.assignment.starts_at,
        ) +
        ((event.clientX - drag.x) / drag.width) * duration.value;
    const hours =
        drag.edge === 'move'
            ? event.clientX === drag.x
                ? {
                      starts_at: drag.assignment.starts_at,
                      ends_at: drag.assignment.ends_at,
                  }
                : translatedAssignment(props.shift, drag.assignment, minute)
            : resizedAssignment(
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
    const { assignment, hours, edge, breakIndex } = dragging.value;
    cancelDrag();
    if (!props.enabled || !props.canManage) return;
    if (edge === 'break') {
        if (
            hours.breaks &&
            hours.breaks[breakIndex].starts_at !==
                assignment.breaks[breakIndex].starts_at
        )
            emit('move-break', { assignment, breaks: hours.breaks });
        return;
    }
    if (
        props.enabled &&
        (assignment.starts_at !== hours.starts_at ||
            assignment.ends_at !== hours.ends_at)
    )
        emit('resize', { assignment, hours });
};
const moveBreakKey = (event, assignment, index) => {
    if (!props.enabled || !props.canManage) return;
    const delta =
        event.key === 'ArrowLeft'
            ? -15
            : event.key === 'ArrowRight'
              ? 15
              : null;
    if (delta === null) return;
    event.preventDefault();
    const moved = translatedPersonalBreak(
        assignment,
        index,
        wallMinutes(assignment.breaks[index].starts_at) + delta,
    );
    if (moved && moved.starts_at !== assignment.breaks[index].starts_at)
        emit('move-break', {
            assignment,
            breaks: assignment.breaks.map((row, i) =>
                i === index ? moved : row,
            ),
        });
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
    const hours =
        edge === 'move'
            ? translatedAssignment(
                  props.shift,
                  assignment,
                  timelineMinute(assignment.starts_at) + delta,
              )
            : resizedAssignment(
                  props.shift,
                  assignment,
                  edge,
                  timelineMinute(
                      edge === 'start'
                          ? assignment.starts_at
                          : assignment.ends_at,
                  ) + delta,
              );
    if (
        hours &&
        (hours.starts_at !== assignment.starts_at ||
            hours.ends_at !== assignment.ends_at)
    )
        emit('resize', { assignment, hours });
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
                            filled: shift.assignment_count,
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
                            : 'hover:bg-transparent'
                    "
                >
                    <TableCell
                        class="sticky left-0 z-10 border-r border-line bg-ground px-3 py-2"
                    >
                        <div
                            v-if="row.assignment?.overlaps.length"
                            class="pointer-events-none absolute inset-0 bg-warning/10"
                            aria-hidden="true"
                        />
                        <div
                            class="relative flex w-[calc(var(--person-width)-1.5rem)] items-center gap-2"
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
                                    <a
                                        v-if="row.assignment?.member_url"
                                        :href="row.assignment.member_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="cursor-pointer truncate text-sm font-semibold hover:text-primary hover:underline focus-visible:text-primary focus-visible:underline"
                                        :title="row.assignment.name"
                                        >{{ row.assignment.name }}</a
                                    >
                                    <span
                                        v-else
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
                                <div
                                    class="mt-1 flex flex-wrap items-center gap-1.5"
                                    data-roster-details
                                >
                                    <span
                                        v-if="
                                            row.assignment?.role_name ||
                                            row.slot?.role_name
                                        "
                                        class="min-w-0 truncate text-xs text-muted"
                                        :title="
                                            row.assignment?.role_name ??
                                            row.slot?.role_name
                                        "
                                    >
                                        {{
                                            row.assignment?.role_name ??
                                            row.slot?.role_name
                                        }}
                                    </span>
                                    <Badge
                                        v-if="row.assignment?.is_supervisor"
                                        pill
                                        variant="primary"
                                        data-supervisor-tag
                                        :title="
                                            $t('team.scheduling.supervisor.tag')
                                        "
                                        :aria-label="
                                            $t('team.scheduling.supervisor.tag')
                                        "
                                        role="img"
                                    >
                                        <Icon
                                            :name="['fas', 'user-tie']"
                                            size="sm"
                                        />
                                    </Badge>
                                    <Tooltip
                                        v-if="
                                            row.assignment &&
                                            mealsForAssignment(
                                                shift.meals ?? [],
                                                row.assignment.id,
                                            ).length
                                        "
                                        :label="
                                            $t(
                                                'team.scheduling.meals.badge_hint',
                                            ) +
                                            ' ' +
                                            mealsForAssignment(
                                                shift.meals ?? [],
                                                row.assignment.id,
                                            )
                                                .map(
                                                    (meal) =>
                                                        `${meal.meal.name} · ${meal.meal.starts_at}`,
                                                )
                                                .join(', ')
                                        "
                                    >
                                        <Badge
                                            pill
                                            class="text-muted"
                                        >
                                            <Icon
                                                :name="['fas', 'utensils']"
                                                size="sm"
                                            />
                                            <span
                                                v-if="
                                                    mealsForAssignment(
                                                        shift.meals ?? [],
                                                        row.assignment.id,
                                                    ).length > 1
                                                "
                                                class="ml-1"
                                                >{{
                                                    mealsForAssignment(
                                                        shift.meals ?? [],
                                                        row.assignment.id,
                                                    ).length
                                                }}</span
                                            >
                                        </Badge>
                                        <template #content>
                                            {{
                                                $t(
                                                    'team.scheduling.meals.badge_hint',
                                                )
                                            }}
                                            <span
                                                v-for="meal in mealsForAssignment(
                                                    shift.meals ?? [],
                                                    row.assignment.id,
                                                )"
                                                :key="meal._key ?? meal.id"
                                                class="mt-1 block"
                                                >{{ meal.meal.name }} ·
                                                {{ meal.meal.starts_at }}</span
                                            >
                                        </template>
                                    </Tooltip>
                                </div>
                            </div>
                            <span
                                v-if="row.assignment?.overlaps.length"
                                class="group relative ml-auto inline-flex shrink-0 text-warning"
                                :aria-label="warningDetails(row.assignment)"
                                :aria-describedby="`${warningTooltipId}-${row.key}`"
                                tabindex="0"
                            >
                                <Icon
                                    :name="['fas', 'triangle-exclamation']"
                                    aria-hidden="true"
                                />
                                <span
                                    :id="`${warningTooltipId}-${row.key}`"
                                    role="tooltip"
                                    class="pointer-events-none absolute bottom-full left-0 z-50 mb-2 hidden w-56 rounded-lg bg-charcoal px-3 py-2 text-xs whitespace-pre-line text-white shadow-lg group-hover:block group-focus-visible:block sm:right-0 sm:left-auto"
                                    >{{ warningDetails(row.assignment) }}</span
                                >
                            </span>
                        </div>
                        <p
                            v-if="
                                row.assignment?.error ||
                                row.assignment?.validation_errors?.length
                            "
                            class="mt-1 mb-0 text-xs text-danger"
                            role="alert"
                        >
                            {{
                                row.assignment.validation_errors?.length
                                    ? row.assignment.validation_errors.join(
                                          '; ',
                                      )
                                    : row.assignment.error
                            }}
                        </p>
                    </TableCell>
                    <TableCell class="p-0">
                        <div
                            class="relative isolate h-14 w-[var(--timeline-width)]"
                            data-roster-canvas
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
                            <div
                                v-for="other in otherShifts(row.assignment)"
                                :key="other.shift_id"
                                class="absolute top-3 left-[var(--bar-start)] h-7 w-[var(--bar-width)] rounded-lg border"
                                :class="otherShiftTokens(other).classes"
                                :style="geometry(other.interval)"
                                :title="`${other.shift_name || $t('team.scheduling.unnamed_shift')} · ${shiftHoursLabel(other)}`"
                                data-other-shift
                            >
                                <span
                                    class="pointer-events-none absolute inset-0 rounded-lg bg-[repeating-linear-gradient(135deg,transparent,transparent_4px,currentColor_4px,currentColor_6px)] opacity-20"
                                    aria-hidden="true"
                                />
                                <span
                                    class="pointer-events-none relative flex h-full items-center gap-2 px-2 text-xs font-semibold"
                                >
                                    <span
                                        v-if="
                                            fitsShiftName(
                                                other.interval,
                                                other.shift_name ||
                                                    $t(
                                                        'team.scheduling.unnamed_shift',
                                                    ),
                                            )
                                        "
                                        class="min-w-0 flex-1 truncate"
                                        >{{
                                            other.shift_name ||
                                            $t('team.scheduling.unnamed_shift')
                                        }}</span
                                    >
                                    <span
                                        class="ml-auto shrink-0 whitespace-nowrap"
                                        >{{ totalTime(other) }}</span
                                    >
                                </span>
                            </div>
                            <template
                                v-if="
                                    row.assignment &&
                                    personInterval(row.assignment)
                                "
                            >
                                <div
                                    class="absolute top-3 left-[var(--bar-start)] h-7 w-[var(--bar-width)] rounded-lg border transition duration-150"
                                    :class="[
                                        tokens.highlightRing,
                                        row.assignment.overlaps.length
                                            ? 'border-warning/30 bg-warning/20'
                                            : tokens.classes,
                                        enabled && canManage
                                            ? 'hover:ring-2 hover:brightness-95'
                                            : '',
                                        {
                                            'ring-2 brightness-95':
                                                enabled &&
                                                canManage &&
                                                dragging &&
                                                dragging.edge !== 'break' &&
                                                dragging.assignment.id ===
                                                    row.assignment.id,
                                        },
                                    ]"
                                    :style="
                                        geometry(personInterval(row.assignment))
                                    "
                                    :title="assignmentTitle(row.assignment)"
                                    data-person-bar
                                >
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        role="slider"
                                        class="absolute inset-y-0 right-3 left-3 z-10 touch-none rounded-sm focus-visible:outline-2 focus-visible:outline-primary"
                                        :class="
                                            dragging?.edge === 'move' &&
                                            dragging.assignment.id ===
                                                row.assignment.id
                                                ? 'cursor-grabbing'
                                                : 'cursor-grab'
                                        "
                                        :disabled="!enabled"
                                        :aria-label="
                                            $t(
                                                'team.scheduling.roster.move_person',
                                                { name: row.assignment.name },
                                            )
                                        "
                                        :aria-valuemin="
                                            timelineMinute(shift.starts_at)
                                        "
                                        :aria-valuemax="
                                            timelineMinute(shift.ends_at) -
                                            (timelineMinute(
                                                row.assignment.ends_at,
                                            ) -
                                                timelineMinute(
                                                    row.assignment.starts_at,
                                                ))
                                        "
                                        :aria-valuenow="
                                            timelineMinute(
                                                row.assignment.starts_at,
                                            )
                                        "
                                        :aria-valuetext="
                                            shiftHoursLabel(row.assignment)
                                        "
                                        :title="
                                            $t(
                                                'team.scheduling.roster.move_hint',
                                            )
                                        "
                                        data-move-handle
                                        @pointerdown="
                                            beginDrag(
                                                $event,
                                                row.assignment,
                                                'move',
                                            )
                                        "
                                        @keydown="
                                            resizeKey(
                                                $event,
                                                row.assignment,
                                                'move',
                                            )
                                        "
                                    />
                                    <span
                                        v-if="
                                            otherShifts(row.assignment)
                                                .length &&
                                            fitsShiftName(
                                                personInterval(row.assignment),
                                                shift.name,
                                            )
                                        "
                                        class="pointer-events-none relative z-10 block truncate px-4 py-1 pr-20 text-xs font-semibold"
                                        >{{ shift.name }}</span
                                    >
                                    <span
                                        class="pointer-events-none absolute inset-y-0 right-4 z-10 flex items-center text-xs font-semibold whitespace-nowrap"
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
                                <component
                                    :is="canManage ? 'button' : 'span'"
                                    v-for="interval in breakIntervals(
                                        row.assignment,
                                    )"
                                    :key="`break-${interval.breakIndex}`"
                                    class="absolute top-3 left-[var(--bar-start)] z-30 flex h-7 w-[var(--bar-width)] touch-none items-center justify-center overflow-hidden border-y transition duration-150 focus-visible:outline-2 focus-visible:outline-primary"
                                    :class="[
                                        breakPattern,
                                        tokens.highlightRing,
                                        enabled && canManage
                                            ? isDraggingBreak(
                                                  row.assignment,
                                                  interval.breakIndex,
                                              )
                                                ? 'cursor-grabbing'
                                                : 'cursor-grab'
                                            : 'cursor-default',
                                        enabled && canManage
                                            ? 'hover:ring-2 hover:brightness-95'
                                            : '',
                                        {
                                            'ring-2 brightness-95':
                                                enabled &&
                                                canManage &&
                                                isDraggingBreak(
                                                    row.assignment,
                                                    interval.breakIndex,
                                                ),
                                            'rounded-l-lg border-l':
                                                interval.atStart,
                                            'rounded-r-lg border-r':
                                                interval.atEnd,
                                        },
                                        tokens.classes
                                            .split(' ')
                                            .find((token) =>
                                                token.startsWith('border-'),
                                            ),
                                    ]"
                                    :style="geometry(interval)"
                                    :type="canManage ? 'button' : undefined"
                                    :role="canManage ? 'slider' : undefined"
                                    :disabled="canManage ? !enabled : undefined"
                                    :aria-label="
                                        canManage
                                            ? $t(
                                                  'team.scheduling.breaks.move_person',
                                                  {
                                                      name: row.assignment.name,
                                                      time: interval.starts_at.slice(
                                                          11,
                                                          16,
                                                      ),
                                                  },
                                              )
                                            : undefined
                                    "
                                    :aria-valuemin="
                                        canManage
                                            ? Math.ceil(
                                                  wallMinutes(
                                                      row.assignment.starts_at,
                                                  ) / 15,
                                              ) * 15
                                            : undefined
                                    "
                                    :aria-valuemax="
                                        canManage
                                            ? Math.floor(
                                                  (wallMinutes(
                                                      row.assignment.ends_at,
                                                  ) -
                                                      interval.duration_minutes) /
                                                      15,
                                              ) * 15
                                            : undefined
                                    "
                                    :aria-valuenow="
                                        canManage
                                            ? wallMinutes(interval.starts_at)
                                            : undefined
                                    "
                                    :aria-valuetext="
                                        canManage ? interval.title : undefined
                                    "
                                    :title="
                                        canManage
                                            ? `${interval.title}\n${$t('team.scheduling.breaks.move_hint')}`
                                            : interval.title
                                    "
                                    data-person-break-hatch
                                    @pointerdown.stop="
                                        beginDrag(
                                            $event,
                                            row.assignment,
                                            'break',
                                            interval.breakIndex,
                                        )
                                    "
                                    @keydown.stop="
                                        moveBreakKey(
                                            $event,
                                            row.assignment,
                                            interval.breakIndex,
                                        )
                                    "
                                >
                                    <Icon
                                        :name="['fas', 'mug-saucer']"
                                        class="shrink-0 text-xs"
                                    />
                                    <span class="sr-only">{{
                                        interval.title
                                    }}</span>
                                </component>
                                <span
                                    v-if="
                                        row.assignment.overlaps.length &&
                                        !otherShifts(row.assignment).length
                                    "
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
                                        role: row.slot?.role_name,
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
                                        { role: row.slot?.role_name },
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
            v-if="rows.length || hasBreaks"
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
            <span
                v-if="hasBreaks"
                class="flex items-center gap-1.5"
                data-break-legend
                ><span
                    class="h-2.5 w-4 rounded-sm"
                    :class="breakPattern"
                    aria-hidden="true"
                />{{ $t('team.scheduling.roster.legend_break') }}</span
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
