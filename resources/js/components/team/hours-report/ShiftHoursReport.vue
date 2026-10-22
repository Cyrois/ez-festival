<script setup>
import HoursReportEmploymentFilter from './HoursReportEmploymentFilter.vue';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Button } from '../../ui/button';
import { Input } from '../../ui/input';
import { FormField } from '../../ui/form-field';
import { Icon } from '../../ui/icon';
import { EmptyState } from '../../ui/empty-state';
import ShiftHoursReportTable from './ShiftHoursReportTable.vue';
import { scheduleDay, scheduleDateLabel } from '../../../lib/scheduleTimeline';

const employmentType = defineModel('employmentType', {
    type: String,
    default: 'all',
});
const selectedDate = defineModel('date', { type: String, required: true });
const props = defineProps({
    employmentOptions: { type: Array, default: () => [] },
    day: { type: Object, default: null },
    people: { type: Object, default: null },
    rows: { type: Array, required: true },
    openedShift: { type: Number, default: null },
    dateError: { type: String, default: '' },
    canViewTeam: { type: Boolean, default: false },
    locale: { type: String, default: 'en' },
});
const emit = defineEmits(['load-day', 'load-people', 'toggle-shift']);
const dateLabel = computed(() =>
    selectedDate.value
        ? scheduleDateLabel(selectedDate.value, props.locale, {
              year: 'numeric',
          })
        : '',
);
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-start gap-3">
            <FormField
                html-for="hours-shift-day"
                :label="$t('team.reports.day')"
                :error="dateError"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        variant="outline-primary"
                        size="icon"
                        :disabled="!selectedDate"
                        :aria-label="$t('team.scheduling.grid.previous_day')"
                        @click="selectedDate = scheduleDay(selectedDate, -1)"
                    >
                        <Icon :name="['fas', 'chevron-left']" />
                    </Button>
                    <span class="text-sm font-semibold">{{ dateLabel }}</span>
                    <Button
                        variant="outline-primary"
                        size="icon"
                        :disabled="!selectedDate"
                        :aria-label="$t('team.scheduling.grid.next_day')"
                        @click="selectedDate = scheduleDay(selectedDate, 1)"
                    >
                        <Icon :name="['fas', 'chevron-right']" />
                    </Button>
                    <Input
                        id="hours-shift-day"
                        v-model="selectedDate"
                        type="date"
                        :invalid="Boolean(dateError)"
                        :aria-label="$t('team.scheduling.grid.select_day')"
                        class="w-full sm:w-40"
                    />
                </div>
            </FormField>
            <HoursReportEmploymentFilter
                v-model="employmentType"
                :options="employmentOptions"
            />
        </div>
        <section
            :aria-label="$t('team.reports.by_shift')"
            :aria-busy="day?.status === 'loading'"
            class="space-y-3"
        >
            <p
                v-if="day?.status === 'loading'"
                role="status"
                class="rounded-lg border border-line bg-white p-6 text-sm text-muted"
            >
                {{ $t('team.reports.shifts_loading') }}
            </p>
            <p
                v-else-if="day?.status === 'failed'"
                role="alert"
                class="rounded-lg border border-line bg-white p-6 text-sm text-danger"
            >
                {{ $t('team.reports.shifts_load_failed') }}
                <Button
                    variant="link"
                    @click="emit('load-day')"
                    >{{ $t('team.reports.retry') }}</Button
                >
            </p>
            <EmptyState
                v-else-if="day?.status === 'loaded' && !rows.length"
                :title="$t('team.reports.shifts_empty')"
                :description="$t('team.reports.shifts_empty_description')"
            />
            <ShiftHoursReportTable
                v-else-if="day?.status === 'loaded'"
                :rows="rows"
                :employment-type="employmentType"
                :opened-shift="openedShift"
                :people="people"
                :can-view-team="canViewTeam"
                @toggle-shift="emit('toggle-shift', $event)"
                @load-people="emit('load-people')"
            />
        </section>
    </div>
</template>
