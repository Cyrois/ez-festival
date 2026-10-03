<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { labelTokens } from '../../lib/labelTokens';
import { Link } from '@inertiajs/vue3';
import { Icon } from '../ui/icon';
import { Button } from '../ui/button';
import {
    SCHEDULE_CELL_WIDTH,
    SCHEDULE_LABEL_WIDTH,
    SCHEDULE_SLOT_COUNT,
    scheduleLanes,
    scheduleInitialScroll,
    scheduleSelection,
    scheduleShiftIsFilled,
    scheduleSlot,
    scheduleShiftHref,
} from '../../lib/scheduleTimeline';

const props = defineProps({
    rows: { type: Array, required: true },
    date: { type: String, required: true },
    firstShiftMinute: { type: Number, default: null },
    canCreate: { type: Boolean, default: false },
    empty: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    hasMore: { type: Boolean, default: false },
});
const emit = defineEmits(['create', 'load-more']);
const viewport = ref(null);
const drag = ref(null);
const helperDismissed = ref(false);
const slots = Array.from({ length: SCHEDULE_SLOT_COUNT }, (_, index) => index);
const layout = computed(() =>
    props.rows.map((row) => {
        const shifts = scheduleLanes(row.shifts, props.date);
        const lanes = Math.max(1, ...shifts.map((shift) => shift.lane + 1));
        return { ...row, shifts, height: Math.max(80, lanes * 64 + 16) };
    }),
);
const selection = computed(() =>
    drag.value
        ? scheduleSelection(
              props.date,
              drag.value.locationId,
              drag.value.anchor,
              drag.value.current,
          )
        : null,
);
const geometry = (start, end, lane = 0) => ({
    '--bar-start': `${(start / 30) * SCHEDULE_CELL_WIDTH + 2}px`,
    '--bar-width': `${Math.max(4, ((end - start) / 30) * SCHEDULE_CELL_WIDTH - 4)}px`,
    '--bar-top': `${lane * 64 + 10}px`,
});
let capturedCanvas;
let animation;
let pointerX = 0;
const cancelDrag = () => {
    if (animation) cancelAnimationFrame(animation);
    animation = undefined;
    const pointerId = drag.value?.pointerId;
    drag.value = null;
    if (
        pointerId !== undefined &&
        capturedCanvas?.hasPointerCapture?.(pointerId)
    ) {
        capturedCanvas.releasePointerCapture(pointerId);
    }
    capturedCanvas = null;
};
const autoScroll = () => {
    if (!drag.value || !viewport.value || !capturedCanvas) return;
    const rect = viewport.value.getBoundingClientRect();
    const left = rect.left + SCHEDULE_LABEL_WIDTH;
    const direction =
        pointerX > rect.right - 36 ? 1 : pointerX < left + 36 ? -1 : 0;
    if (direction) {
        viewport.value.scrollLeft += direction * 12;
        drag.value.current = scheduleSlot(
            pointerX,
            capturedCanvas.getBoundingClientRect().left,
        );
    }
    animation = requestAnimationFrame(autoScroll);
};
const startDrag = (event, row) => {
    if (
        !props.canCreate ||
        event.button !== 0 ||
        event.isPrimary === false ||
        event.target.closest('a, button')
    )
        return;
    cancelDrag();
    capturedCanvas = event.currentTarget;
    pointerX = event.clientX;
    const slot = scheduleSlot(
        pointerX,
        capturedCanvas.getBoundingClientRect().left,
    );
    drag.value = {
        locationId: row.id,
        anchor: slot,
        current: slot,
        pointerId: event.pointerId,
        x: event.clientX,
        moved: false,
    };
    capturedCanvas.setPointerCapture?.(event.pointerId);
    event.preventDefault();
};
const moveDrag = (event) => {
    if (
        !drag.value ||
        event.pointerId !== drag.value.pointerId ||
        !capturedCanvas
    )
        return;
    pointerX = event.clientX;
    drag.value.current = scheduleSlot(
        pointerX,
        capturedCanvas.getBoundingClientRect().left,
    );
    drag.value.moved ||= Math.abs(pointerX - drag.value.x) > 4;
    if (drag.value.moved && !animation)
        animation = requestAnimationFrame(autoScroll);
};
const finishDrag = (event) => {
    if (!drag.value || event.pointerId !== drag.value.pointerId) return;
    moveDrag(event);
    const completed =
        drag.value.moved && props.canCreate ? selection.value : null;
    cancelDrag();
    if (completed) emit('create', completed);
};
const onKeydown = (event) => {
    if (event.key === 'Escape') cancelDrag();
};
const onScroll = () => {
    if (
        !props.loading &&
        props.hasMore &&
        viewport.value &&
        viewport.value.scrollHeight -
            viewport.value.scrollTop -
            viewport.value.clientHeight <
            120
    ) {
        emit('load-more');
    }
};
watch(() => [props.date, props.canCreate], cancelDrag);
watch(
    () => [props.date, props.firstShiftMinute],
    async () => {
        await nextTick();
        if (viewport.value) {
            viewport.value.scrollTop = 0;
            viewport.value.scrollLeft = scheduleInitialScroll(
                props.firstShiftMinute,
            );
        }
    },
);
onMounted(() => {
    viewport.value.scrollLeft = scheduleInitialScroll(props.firstShiftMinute);
    window.addEventListener('keydown', onKeydown);
});
onUnmounted(() => {
    cancelDrag();
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div class="relative">
        <div
            ref="viewport"
            class="relative max-h-[70vh] overflow-auto rounded-lg border border-line bg-ground focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            :aria-label="$t('team.scheduling.grid.label')"
            :aria-busy="loading"
            role="region"
            tabindex="0"
            @scroll="onScroll"
        >
            <div class="relative min-h-40 w-[calc(10rem+168rem)]">
                <div
                    class="sticky top-0 z-20 flex h-8 border-b border-line bg-page"
                >
                    <div
                        class="sticky left-0 z-30 flex w-40 shrink-0 items-center border-r border-line bg-page px-3 text-xs font-semibold text-muted"
                    >
                        {{ $t('team.scheduling.table.location') }}
                    </div>
                    <div class="grid grid-cols-[repeat(48,3.5rem)]">
                        <div
                            v-for="slot in slots"
                            :key="slot"
                            class="border-r border-line px-2 pt-2 text-xs font-semibold"
                        >
                            <template v-if="slot % 2 === 0"
                                >{{
                                    String(slot / 2).padStart(2, '0')
                                }}:00</template
                            >
                        </div>
                    </div>
                </div>
                <div
                    v-for="row in layout"
                    :key="row.id"
                    class="flex border-b border-line last:border-b-0"
                    :style="{ '--row-height': row.height + 'px' }"
                >
                    <div
                        class="sticky left-0 z-10 flex min-h-[var(--row-height)] w-40 shrink-0 flex-col justify-center border-r border-line bg-ground px-3"
                    >
                        <span class="text-sm font-semibold">{{
                            row.name
                        }}</span>
                        <span class="mt-0.5 text-xs text-muted">{{
                            $t(
                                row.shifts.length
                                    ? row.shifts.length === 1
                                        ? 'team.scheduling.grid.one_shift'
                                        : 'team.scheduling.grid.shift_count'
                                    : 'team.scheduling.grid.no_shifts',
                                { count: row.shifts.length },
                            )
                        }}</span>
                    </div>
                    <div
                        class="relative h-[var(--row-height)] w-[168rem] shrink-0"
                        :class="canCreate ? 'cursor-crosshair touch-pan-y' : ''"
                        :data-location-id="row.id"
                        @pointerdown="startDrag($event, row)"
                        @pointermove="moveDrag"
                        @pointerup="finishDrag"
                        @pointercancel="cancelDrag"
                        @lostpointercapture="cancelDrag"
                    >
                        <div
                            class="absolute inset-0 grid grid-cols-[repeat(48,3.5rem)]"
                            aria-hidden="true"
                        >
                            <div
                                v-for="slot in slots"
                                :key="slot"
                                class="border-r border-line/60 transition-colors"
                                :class="canCreate ? 'hover:bg-page' : ''"
                                data-schedule-cell
                            />
                        </div>
                        <Link
                            v-for="shift in row.shifts"
                            :key="shift.id"
                            :href="scheduleShiftHref(shift.id, date)"
                            class="absolute top-[var(--bar-top)] left-[var(--bar-start)] z-[1] flex h-14 w-[var(--bar-width)] min-w-1 flex-col justify-center overflow-hidden rounded-lg border px-2 text-xs no-underline focus-visible:z-10 focus-visible:ring-[3px] focus-visible:ring-secondary focus-visible:outline-none"
                            :class="
                                !scheduleShiftIsFilled(shift)
                                    ? [
                                          labelTokens[shift.color ?? 'teal']
                                              ?.classes,
                                          labelTokens[shift.color ?? 'teal']
                                              ?.unfilledHover,
                                          'border-dashed pr-7 hover:border-solid',
                                      ]
                                    : [
                                          labelTokens[shift.color ?? 'teal']
                                              ?.solid,
                                          'pr-7 hover:opacity-90',
                                      ]
                            "
                            :style="
                                geometry(
                                    shift.interval.start,
                                    shift.interval.end,
                                    shift.lane,
                                )
                            "
                            :title="`${shift.name || $t('team.scheduling.unnamed_shift')} · ${shift.starts_at.replace('T', ' ')} – ${shift.ends_at.replace('T', ' ')}`"
                            :aria-label="
                                $t('team.scheduling.grid.open_shift', {
                                    name:
                                        shift.name ||
                                        $t('team.scheduling.unnamed_shift'),
                                    start: shift.starts_at.replace('T', ' '),
                                    end: shift.ends_at.replace('T', ' '),
                                    filled: shift.filled_count,
                                    needed: shift.total_needs,
                                })
                            "
                        >
                            <span class="truncate font-semibold">{{
                                shift.name ||
                                $t('team.scheduling.unnamed_shift')
                            }}</span>
                            <span class="mt-0.5 whitespace-nowrap">
                                {{
                                    $t('team.scheduling.grid.filled', {
                                        filled: shift.filled_count,
                                        needed: shift.total_needs,
                                    })
                                }}
                            </span>
                            <Icon
                                v-if="scheduleShiftIsFilled(shift)"
                                :name="['fas', 'check']"
                                class="absolute top-1/2 right-2 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Icon
                                v-if="!scheduleShiftIsFilled(shift)"
                                :name="['fas', 'circle-exclamation']"
                                class="absolute top-1/2 right-2 -translate-y-1/2 text-warning"
                                aria-hidden="true"
                            />
                        </Link>
                        <div
                            v-if="selection && selection.location_id === row.id"
                            class="pointer-events-none absolute top-1 bottom-1 left-[var(--bar-start)] z-[2] w-[var(--bar-width)] rounded-lg border border-primary bg-primary/15"
                            :style="geometry(selection.start, selection.end)"
                            aria-hidden="true"
                        />
                    </div>
                </div>
            </div>
            <div
                v-if="loading || hasMore"
                class="sticky left-0 flex w-full justify-center bg-ground p-3"
            >
                <span
                    v-if="loading"
                    class="text-sm text-muted"
                    role="status"
                    >{{ $t('data_table.loading') }}</span
                >
                <Button
                    v-else
                    variant="cancel"
                    @click="emit('load-more')"
                    >{{ $t('team.scheduling.grid.more_locations') }}</Button
                >
            </div>
        </div>
        <div
            v-if="empty && rows.length && !helperDismissed"
            class="pointer-events-none absolute inset-x-0 top-8 bottom-0 z-10 flex items-center justify-center px-4"
        >
            <div
                class="max-w-sm rounded-lg border border-dashed border-line bg-ground/95 p-5 text-center"
            >
                <Icon
                    :name="['fas', 'arrow-pointer']"
                    class="mb-2 text-muted"
                />
                <p class="m-0 font-semibold">
                    {{
                        $t(
                            canCreate
                                ? 'team.scheduling.grid.empty_title'
                                : 'team.scheduling.grid.empty_read_only',
                        )
                    }}
                </p>
                <p class="mt-1 mb-3 text-xs text-muted">
                    {{
                        $t(
                            canCreate
                                ? 'team.scheduling.grid.empty_description'
                                : 'team.scheduling.grid.empty_view_description',
                        )
                    }}
                </p>
                <button
                    type="button"
                    class="pointer-events-auto cursor-pointer text-xs font-semibold text-secondary hover:underline focus-visible:outline-primary"
                    @click="helperDismissed = true"
                >
                    {{ $t('ui.dialog.close') }}
                </button>
            </div>
        </div>
    </div>
</template>
