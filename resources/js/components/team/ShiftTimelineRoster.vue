<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { CardTitle } from '../ui/card';
import { Avatar } from '../ui/avatar';
import { Badge } from '../ui/badge';
import { Button } from '../ui/button';
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
import { scheduleRosterRows } from '../../lib/shiftAssignments';
import {
    timelineMinute,
    timelineIntersection,
    shiftTimelineTicks,
    shiftOverlapIntervals,
    shiftHoursLabel,
    SCHEDULE_CELL_WIDTH,
    SCHEDULE_SLOT_MINUTES,
} from '../../lib/scheduleTimeline';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    shift: { type: Object, required: true },
    enabled: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    disabledReason: { type: String, default: '' },
});
defineEmits(['assign', 'edit', 'remove']);
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
onUnmounted(() => observer?.disconnect());
const rows = computed(() => scheduleRosterRows(props.shift));
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
            assignment.name.length * 6 + 24
    );
};
const fullShift = (assignment) =>
    assignment.starts_at === props.shift.starts_at &&
    assignment.ends_at === props.shift.ends_at;
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
                        $t('team.scheduling.slots.filled', {
                            filled: shift.filled_count,
                            count: shift.total_needs,
                        })
                    }}
                </p>
            </div>
            <slot name="header-actions" />
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
                            class="relative h-14 w-[var(--timeline-width)]"
                            :aria-label="shiftHoursLabel(bounds)"
                        >
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
                                    class="absolute top-2 text-xs font-normal whitespace-nowrap"
                                    :class="
                                        index === 0
                                            ? 'left-1'
                                            : index === ticks.length - 1
                                              ? 'right-1'
                                              : 'left-0 -translate-x-1/2'
                                    "
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
                <TableRow>
                    <TableCell
                        class="sticky left-0 z-10 border-r border-line bg-ground px-3"
                    >
                        <div class="w-[calc(var(--person-width)-1.5rem)]">
                            <p
                                class="m-0 truncate text-sm font-bold"
                                :title="shift.name"
                            >
                                {{
                                    shift.name ||
                                    $t('team.scheduling.unnamed_shift')
                                }}
                            </p>
                            <p class="mt-1 mb-0 text-xs text-muted">
                                {{ shift.location }} ·
                                {{ shiftHoursLabel(shift) }}
                            </p>
                        </div>
                    </TableCell>
                    <TableCell class="p-0">
                        <div
                            class="relative flex min-h-20 w-[var(--timeline-width)] items-center"
                        >
                            <div
                                class="absolute left-[var(--bar-start)] flex h-7 w-[var(--bar-width)] items-center gap-2 overflow-hidden rounded-lg border px-3 text-xs font-semibold"
                                :style="geometry(shiftInterval)"
                                :class="tokens.solid"
                                data-roster-summary
                            >
                                <span>{{
                                    $t('team.scheduling.grid.filled', {
                                        filled: shift.filled_count,
                                        needed: shift.total_needs,
                                    })
                                }}</span>
                                <span v-if="shift.extra_count"
                                    >·
                                    {{
                                        $t(
                                            shift.extra_count === 1
                                                ? 'team.scheduling.roster.extra_one'
                                                : 'team.scheduling.roster.extra_many',
                                            { count: shift.extra_count },
                                        )
                                    }}</span
                                >
                            </div>
                        </div>
                    </TableCell>
                    <TableCell
                        v-if="canManage"
                        class="sticky right-0 z-10 border-l border-line bg-ground px-2"
                    />
                </TableRow>
                <TableRow
                    v-for="row in rows"
                    :key="row.key"
                    :data-roster-row="row.key"
                    :class="
                        row.assignment?.overlaps.length
                            ? 'bg-warning/10 hover:bg-warning/10'
                            : ''
                    "
                >
                    <TableCell
                        class="sticky left-0 z-10 border-r border-line bg-ground px-3"
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
                                        row.assignment
                                            ? assignmentTitle(row.assignment)
                                            : row.slot.role_name
                                    "
                                >
                                    {{
                                        row.assignment?.role_name ??
                                        row.slot.role_name
                                    }}
                                    ·
                                    {{
                                        !row.assignment ||
                                        fullShift(row.assignment)
                                            ? $t(
                                                  'team.scheduling.assignments.full_shift',
                                              )
                                            : shiftHoursLabel(row.assignment)
                                    }}
                                </p>
                            </div>
                        </div>
                    </TableCell>
                    <TableCell class="p-0">
                        <div
                            class="relative isolate h-18 w-[var(--timeline-width)]"
                        >
                            <div
                                class="pointer-events-none absolute inset-0"
                                aria-hidden="true"
                            >
                                <div
                                    v-for="tick in ticks"
                                    :key="tick.minute"
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
                                    class="absolute top-6 left-[var(--bar-start)] h-7 w-[var(--bar-width)] rounded-lg border"
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
                                        class="relative z-10 block truncate px-2 py-1 text-xs font-semibold"
                                        :class="
                                            row.assignment.overlaps.length
                                                ? 'w-1/2'
                                                : ''
                                        "
                                        >{{ row.assignment.name }}</span
                                    >
                                </div>
                                <div
                                    v-for="(
                                        overlap, index
                                    ) in shiftOverlapIntervals(
                                        row.assignment,
                                        shift,
                                    )"
                                    :key="index"
                                    class="pointer-events-none absolute top-6 left-[var(--bar-start)] h-7 w-[var(--bar-width)] rounded-lg bg-[repeating-linear-gradient(135deg,transparent,transparent_4px,currentColor_4px,currentColor_6px)] text-warning opacity-40"
                                    :style="geometry(overlap)"
                                    aria-hidden="true"
                                    data-overlap-hatch
                                />
                                <span
                                    v-if="shortBar(row.assignment)"
                                    class="absolute top-1 left-[var(--bar-start)] z-10 max-w-64 truncate text-xs font-semibold"
                                    :style="
                                        geometry(personInterval(row.assignment))
                                    "
                                    :title="row.assignment.name"
                                    data-short-label
                                    >{{ row.assignment.name }}</span
                                >
                                <span
                                    v-if="row.assignment.overlaps.length"
                                    class="absolute left-[var(--bar-start)] z-10 w-[var(--bar-width)] truncate px-2 text-right text-xs text-warning"
                                    :class="
                                        shortBar(row.assignment)
                                            ? 'top-14'
                                            : 'top-7'
                                    "
                                    :style="
                                        geometry(
                                            shortBar(row.assignment)
                                                ? personInterval(row.assignment)
                                                : {
                                                      start:
                                                          (personInterval(
                                                              row.assignment,
                                                          ).start +
                                                              personInterval(
                                                                  row.assignment,
                                                              ).end) /
                                                          2,
                                                      end: personInterval(
                                                          row.assignment,
                                                      ).end,
                                                  },
                                        )
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
                            </template>
                            <div
                                v-else
                                class="absolute top-5 left-[var(--bar-start)] flex h-10 w-[var(--bar-width)] items-center gap-3 rounded-lg border border-dashed border-line bg-ground px-2 text-xs font-semibold text-charcoal"
                                :style="geometry(shiftInterval)"
                                data-open-bar
                            >
                                <span
                                    class="pointer-events-none absolute inset-0 rounded-lg bg-[repeating-linear-gradient(135deg,transparent,transparent_7px,currentColor_7px,currentColor_8px)] text-muted opacity-10"
                                    aria-hidden="true"
                                />
                                <span class="relative">{{
                                    $t('team.scheduling.roster.open_role', {
                                        role: row.slot.role_name,
                                    })
                                }}</span>
                                <span
                                    class="relative"
                                    :title="disabledReason"
                                    :tabindex="
                                        !enabled && disabledReason
                                            ? 0
                                            : undefined
                                    "
                                    :aria-label="
                                        !enabled ? disabledReason : undefined
                                    "
                                >
                                    <Button
                                        type="button"
                                        variant="outline-primary"
                                        size="xs"
                                        :disabled="!enabled"
                                        :title="disabledReason"
                                        @click="
                                            enabled && $emit('assign', row.slot)
                                        "
                                        ><Icon
                                            :name="['fas', 'plus']"
                                            size="sm"
                                        />{{
                                            $t(
                                                'team.scheduling.assignments.assign',
                                            )
                                        }}</Button
                                    >
                                </span>
                            </div>
                        </div>
                    </TableCell>
                    <TableCell
                        v-if="canManage"
                        class="sticky right-0 z-10 border-l border-line bg-ground px-2"
                    >
                        <span
                            v-if="row.assignment"
                            class="flex justify-center gap-1"
                            :title="disabledReason"
                            :tabindex="
                                !enabled && disabledReason ? 0 : undefined
                            "
                            :aria-label="!enabled ? disabledReason : undefined"
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
                    class="h-2.5 w-4 rounded-sm border border-dashed border-line bg-[repeating-linear-gradient(135deg,transparent,transparent_7px,currentColor_7px,currentColor_8px)] text-muted/10"
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
    </section>
</template>
