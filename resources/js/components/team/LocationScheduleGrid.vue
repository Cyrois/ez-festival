<script setup>
import { onUnmounted, ref, watch } from 'vue';
import { Button } from '../ui/button';
import ScheduleTimeline from './ScheduleTimeline.vue';

const props = defineProps({
    date: { type: String, required: true },
    eventId: { type: Number, required: true },
    locationId: { type: [Number, String], default: '' },
    canCreate: { type: Boolean, default: false },
});
const emit = defineEmits(['create']);
const rows = ref([]);
const totalShifts = ref(null);
const firstShiftMinute = ref(null);
const loading = ref(false);
const failed = ref(false);
const currentPage = ref(0);
const lastPage = ref(1);
let controller;
let generation = 0;

const load = async (page = 1) => {
    if (loading.value) return;
    controller = new AbortController();
    const number = generation;
    loading.value = true;
    failed.value = false;
    try {
        const params = new URLSearchParams({ date: props.date, page });
        if (props.locationId) params.set('location_id', props.locationId);
        const response = await fetch('/team/scheduling/grid?' + params, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (!response.ok) throw new Error('schedule request failed');
        const data = await response.json();
        if (number !== generation) return;
        rows.value = page === 1 ? data.data : [...rows.value, ...data.data];
        totalShifts.value = data.schedule.shift_count;
        firstShiftMinute.value = data.schedule.first_shift_minute;
        currentPage.value = data.meta.current_page;
        lastPage.value = data.meta.last_page;
    } catch (error) {
        if (number === generation && error.name !== 'AbortError')
            failed.value = true;
    } finally {
        if (number === generation) loading.value = false;
    }
};
const refresh = () => {
    generation++;
    controller?.abort();
    rows.value = [];
    totalShifts.value = null;
    firstShiftMinute.value = null;
    currentPage.value = 0;
    lastPage.value = 1;
    loading.value = false;
    load();
};
const loadNext = () => {
    if (!failed.value && currentPage.value < lastPage.value)
        load(currentPage.value + 1);
};
watch(() => [props.date, props.eventId, props.locationId], refresh, {
    immediate: true,
});
onUnmounted(() => {
    generation++;
    controller?.abort();
});
</script>

<template>
    <div>
        <ScheduleTimeline
            :rows="rows"
            :date="date"
            :first-shift-minute="firstShiftMinute"
            :can-create="canCreate"
            :empty="totalShifts === 0 && !loading && !failed"
            :loading="loading"
            :has-more="!failed && currentPage < lastPage"
            @create="emit('create', $event)"
            @load-more="loadNext"
        />
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
        <p class="mt-3 mb-0 text-xs text-muted">
            {{ $t('team.scheduling.grid.legend') }}
        </p>
    </div>
</template>
