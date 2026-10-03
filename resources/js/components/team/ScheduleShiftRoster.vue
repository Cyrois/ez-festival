<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Avatar } from '../ui/avatar';
import { Badge } from '../ui/badge';
import { Button } from '../ui/button';
import { Icon } from '../ui/icon';
import { labelTokens } from '../../lib/labelTokens';
import { scheduleRosterRows } from '../../lib/shiftAssignments';
import {
    scheduleInterval,
    scheduleOverlapIntervals,
    scheduleShiftHref,
    scheduleShiftIsFilled,
} from '../../lib/scheduleTimeline';

const props = defineProps({
    shift: { type: Object, required: true },
    date: { type: String, required: true },
    locationId: { type: [Number, String], required: true },
    geometry: { type: Function, required: true },
    slots: { type: Array, required: true },
    canAssign: { type: Boolean, default: false },
    disabledReason: { type: String, default: '' },
});
const emit = defineEmits(['assign']);
const expanded = ref(false);
const rows = computed(() => scheduleRosterRows(props.shift));
const visible = computed(() =>
    expanded.value ? rows.value : rows.value.slice(0, 4),
);
const interval = computed(() => scheduleInterval(props.shift, props.date));
const tokens = computed(() => labelTokens[props.shift.color ?? 'teal']);
const href = computed(() =>
    scheduleShiftHref(
        props.shift.id,
        props.date,
        'schedule',
        props.locationId,
        'location_shifts',
    ),
);
const hours = (value) =>
    `${value.starts_at.slice(11)}–${value.ends_at.slice(11)}`;
const fullShift = (assignment) =>
    assignment.starts_at === props.shift.starts_at &&
    assignment.ends_at === props.shift.ends_at;
const personGeometry = (assignment) => {
    const clipped = scheduleInterval(assignment, props.date);
    return clipped ? props.geometry(clipped.start, clipped.end) : {};
};
</script>

<template>
    <section class="border-b border-line last:border-b-0">
        <div class="flex h-20">
            <Link
                :href="href"
                class="sticky left-0 z-10 flex w-[var(--label-width)] shrink-0 flex-col justify-center border-r border-line bg-ground px-3 text-charcoal no-underline hover:bg-page focus-visible:outline-primary"
            >
                <span
                    class="truncate text-sm font-bold"
                    :title="shift.name || $t('team.scheduling.unnamed_shift')"
                    >{{
                        shift.name || $t('team.scheduling.unnamed_shift')
                    }}</span
                >
                <span class="mt-1 text-xs text-muted">{{ hours(shift) }}</span>
                <span class="mt-1 flex items-center gap-2 text-xs">
                    {{
                        $t('team.scheduling.grid.filled', {
                            filled: shift.filled_count,
                            needed: shift.total_needs,
                        })
                    }}
                    <Icon
                        v-if="scheduleShiftIsFilled(shift)"
                        :name="['fas', 'check']"
                        aria-hidden="true"
                    />
                </span>
            </Link>
            <div class="relative w-[168rem] shrink-0">
                <div
                    class="absolute inset-0 grid grid-cols-[repeat(48,3.5rem)]"
                    aria-hidden="true"
                >
                    <div
                        v-for="slot in slots"
                        :key="slot"
                        class="border-r border-line/60"
                    />
                </div>
                <Link
                    v-if="interval"
                    :href="href"
                    class="absolute top-3 left-[var(--bar-start)] flex h-14 w-[var(--bar-width)] items-center gap-2 overflow-hidden rounded-lg border px-3 pr-7 text-xs font-semibold no-underline focus-visible:outline-primary"
                    :class="
                        scheduleShiftIsFilled(shift)
                            ? tokens.solid
                            : [
                                  tokens.classes,
                                  tokens.unfilledHover,
                                  'border-dashed hover:border-solid',
                              ]
                    "
                    :style="geometry(interval.start, interval.end)"
                    :title="`${shift.name || $t('team.scheduling.unnamed_shift')} · ${hours(shift)}`"
                >
                    <Icon
                        :name="[
                            'fas',
                            scheduleShiftIsFilled(shift)
                                ? 'check'
                                : 'circle-exclamation',
                        ]"
                        class="absolute top-1/2 right-2 -translate-y-1/2"
                        :class="
                            !scheduleShiftIsFilled(shift) ? 'text-warning' : ''
                        "
                        aria-hidden="true"
                    />
                    <span class="truncate">{{
                        shift.name || $t('team.scheduling.unnamed_shift')
                    }}</span>
                </Link>
            </div>
        </div>
        <div
            v-for="row in visible"
            :key="row.key"
            class="flex min-h-16"
            :class="row.assignment?.overlaps.length ? 'bg-warning/10' : ''"
            :data-roster-row="row.key"
        >
            <div
                class="sticky left-0 z-10 flex w-[var(--label-width)] shrink-0 items-center gap-2 border-r border-line bg-ground px-3 py-2"
            >
                <div
                    v-if="row.assignment?.overlaps.length"
                    class="absolute inset-0 bg-warning/10"
                    aria-hidden="true"
                />
                <template v-if="row.assignment">
                    <Avatar
                        class="relative"
                        :name="row.assignment.name"
                        size="sm"
                    />
                    <div class="relative min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span
                                class="truncate text-sm font-semibold"
                                :title="row.assignment.name"
                                >{{ row.assignment.name }}</span
                            >
                            <Badge
                                v-if="row.assignment.is_extra"
                                pill
                                variant="warning"
                                >{{
                                    $t('team.scheduling.assignments.extra')
                                }}</Badge
                            >
                            <Icon
                                v-if="row.assignment.overlaps.length"
                                :name="['fas', 'circle-info']"
                                class="text-warning"
                            />
                        </div>
                        <p
                            class="m-0 mt-1 truncate text-xs text-muted"
                            :title="row.assignment.role_name"
                        >
                            {{ row.assignment.role_name }} ·
                            {{
                                fullShift(row.assignment)
                                    ? $t(
                                          'team.scheduling.assignments.full_shift',
                                      )
                                    : hours(row.assignment)
                            }}
                        </p>
                    </div>
                </template>
                <template v-else>
                    <span
                        class="min-w-0 flex-1 truncate text-xs text-muted"
                        :title="row.slot.role_name"
                        >{{
                            $t('team.scheduling.roster.open_role', {
                                role: row.slot.role_name,
                            })
                        }}</span
                    >
                    <Button
                        variant="ghost"
                        size="sm"
                        :disabled="!canAssign"
                        :title="disabledReason"
                        @click="emit('assign', row.slot)"
                    >
                        <Icon
                            :name="['fas', 'plus']"
                            size="sm"
                        />
                        {{ $t('team.scheduling.assignments.assign') }}
                    </Button>
                </template>
            </div>
            <div class="relative w-[168rem] shrink-0">
                <div
                    class="absolute inset-0 grid grid-cols-[repeat(48,3.5rem)]"
                    aria-hidden="true"
                >
                    <div
                        v-for="slot in slots"
                        :key="slot"
                        class="border-r border-line/60"
                    />
                </div>
                <template v-if="row.assignment">
                    <div
                        v-if="scheduleInterval(row.assignment, date)"
                        class="absolute top-5 left-[var(--bar-start)] h-6 w-[var(--bar-width)] overflow-hidden rounded-lg border"
                        :class="tokens.classes"
                        :style="personGeometry(row.assignment)"
                        :title="`${row.assignment.name} · ${hours(row.assignment)}`"
                    />
                    <div
                        v-for="(overlap, index) in scheduleOverlapIntervals(
                            row.assignment,
                            date,
                        )"
                        :key="index"
                        class="pointer-events-none absolute top-5 left-[var(--bar-start)] h-6 w-[var(--bar-width)] rounded-lg bg-[repeating-linear-gradient(135deg,transparent,transparent_4px,currentColor_4px,currentColor_6px)] text-warning opacity-40"
                        :style="geometry(overlap.start, overlap.end)"
                        aria-hidden="true"
                        data-overlap-hatch
                    />
                    <div
                        v-if="row.assignment.overlaps.length"
                        class="relative mt-3 ml-[calc(var(--bar-end,0px)+0.5rem)] w-max max-w-80 py-1 text-xs text-warning"
                        :style="personGeometry(row.assignment)"
                    >
                        <p
                            v-for="warning in row.assignment.overlaps"
                            :key="warning.shift_id"
                            class="m-0"
                        >
                            {{
                                $t('team.scheduling.roster.overlap', {
                                    name:
                                        warning.shift_name ||
                                        $t('team.scheduling.unnamed_shift'),
                                    minutes: warning.overlap_minutes,
                                })
                            }}
                        </p>
                    </div>
                </template>
                <div
                    v-else-if="interval"
                    class="absolute top-4 left-[var(--bar-start)] flex h-8 w-[var(--bar-width)] items-center overflow-hidden rounded-lg border border-dashed px-2 text-xs"
                    :class="tokens.classes"
                    :style="geometry(interval.start, interval.end)"
                >
                    {{
                        $t('team.scheduling.roster.open_role', {
                            role: row.slot.role_name,
                        })
                    }}
                </div>
            </div>
        </div>
        <div
            v-if="rows.length > 4"
            class="sticky left-0 w-[var(--label-width)] bg-ground px-3 py-2"
        >
            <Button
                variant="ghost"
                size="sm"
                :aria-expanded="expanded"
                @click="expanded = !expanded"
            >
                {{
                    expanded
                        ? $t('team.scheduling.roster.less')
                        : $t('team.scheduling.roster.more', {
                              count: rows.length - 4,
                          })
                }}
            </Button>
        </div>
        <p
            v-if="!rows.length"
            class="sticky left-0 m-0 w-[var(--label-width)] bg-ground px-3 py-4 text-xs text-muted"
        >
            {{ $t('team.scheduling.slots.empty') }}
        </p>
    </section>
</template>
