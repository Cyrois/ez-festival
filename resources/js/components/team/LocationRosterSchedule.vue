<script setup>
import { onUnmounted, ref, watch } from 'vue';
import { Button } from '../ui/button';
import ScheduleTimeline from './ScheduleTimeline.vue';
import ScheduleShiftRoster from './ScheduleShiftRoster.vue';
import ShiftAssignDialog from './ShiftAssignDialog.vue';

const props = defineProps({
    date: { type: String, required: true },
    eventId: { type: Number, required: true },
    locationId: { type: [Number, String], required: true },
    locationName: { type: String, required: true },
    canAssign: { type: Boolean, default: false },
    disabledReason: { type: String, default: '' },
});
const shifts = ref([]);
const firstShiftMinute = ref(null);
const loading = ref(false);
const failed = ref(false);
const currentPage = ref(0);
const lastPage = ref(1);
const assignment = ref(null);
let controller;
let generation = 0;
const load = async (page = 1) => {
    if (loading.value) return;
    controller = new AbortController();
    const number = generation;
    loading.value = true;
    failed.value = false;
    try {
        const params = new URLSearchParams({
            date: props.date,
            location_id: props.locationId,
            page,
        });
        const response = await fetch('/team/scheduling/roster?' + params, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (!response.ok) throw new Error('schedule request failed');
        const result = await response.json();
        if (number !== generation) return;
        shifts.value =
            page === 1 ? result.data : [...shifts.value, ...result.data];
        firstShiftMinute.value = result.schedule.first_shift_minute;
        currentPage.value = result.meta.current_page;
        lastPage.value = result.meta.last_page;
    } catch (error) {
        if (number === generation && error.name !== 'AbortError')
            failed.value = true;
    } finally {
        if (number === generation) loading.value = false;
    }
};
const refresh = () => {
    ++generation;
    controller?.abort();
    loading.value = false;
    shifts.value = [];
    firstShiftMinute.value = null;
    currentPage.value = 0;
    lastPage.value = 1;
    assignment.value = null;
    load();
};
watch(() => [props.date, props.eventId, props.locationId], refresh, {
    immediate: true,
});
onUnmounted(() => {
    ++generation;
    controller?.abort();
});
const assign = (shift, slot) => {
    if (props.canAssign) assignment.value = { shift, slot };
};
</script>

<template>
    <ScheduleTimeline
        :rows="[]"
        :date="date"
        :location-id="locationId"
        :first-shift-minute="firstShiftMinute"
        :label-width="280"
        :label-text="locationName"
        :more-label="$t('team.scheduling.roster.more_shifts')"
        :loading="loading"
        :has-more="!failed && currentPage < lastPage"
        @load-more="load(currentPage + 1)"
    >
        <template #rows="{ geometry, slots }">
            <ScheduleShiftRoster
                v-for="shift in shifts"
                :key="`${date}-${shift.id}`"
                :shift="shift"
                :date="date"
                :location-id="locationId"
                :geometry="geometry"
                :slots="slots"
                :can-assign="canAssign"
                :disabled-reason="disabledReason"
                @assign="assign(shift, $event)"
            />
            <p
                v-if="!shifts.length && !loading && !failed"
                class="sticky left-0 m-0 w-[var(--label-width)] px-3 py-5 text-sm text-muted"
            >
                {{
                    $t('team.scheduling.roster.empty', {
                        location: locationName,
                    })
                }}
            </p>
        </template>
    </ScheduleTimeline>
    <div
        v-if="failed"
        class="mt-3 flex items-center gap-3 text-sm text-danger"
        role="alert"
    >
        {{ $t('team.scheduling.grid.load_failed') }}
        <Button
            variant="cancel"
            @click="load(currentPage + 1)"
            >{{ $t('team.scheduling.grid.retry') }}</Button
        >
    </div>
    <ShiftAssignDialog
        v-if="assignment"
        :shift="assignment.shift"
        :requirement="assignment.slot"
        :event-id="eventId"
        :return-context="{
            return_tab: 'schedule',
            schedule_date: date,
            schedule_location_id: locationId,
            return_to_schedule: true,
            schedule_view: 'location_shifts',
        }"
        @assigned="refresh"
        @close="assignment = null"
    />
</template>
