<script setup>
import HoursReportCost from './HoursReportCost.vue';
import { Card } from '../../ui/card';
import { formatHoursReportDuration } from '../../../lib/hoursReport';

defineProps({
    summary: { type: Object, default: null },
    compact: { type: Boolean, default: false },
    showingPeople: { type: Boolean, default: false },
    employmentType: { type: String, default: 'all' },
    loading: { type: Boolean, default: false },
});
</script>

<template>
    <dl
        class="grid gap-3"
        :class="showingPeople || compact ? 'grid-cols-3' : 'md:grid-cols-3'"
    >
        <Card
            v-for="type in ['scheduled', 'approved', 'unapproved']"
            :key="type"
            class="min-w-0"
            :class="(showingPeople || compact) && 'max-md:px-2.5 max-md:py-3'"
        >
            <dt
                class="text-xs font-semibold text-muted uppercase max-md:[overflow-wrap:anywhere]"
                :class="(showingPeople || compact) && 'max-md:text-[10px]'"
            >
                {{ $t(`team.reports.${type}`) }}
            </dt>
            <dd
                class="mt-1 text-2xl font-bold tabular-nums max-md:[overflow-wrap:anywhere]"
                :class="(showingPeople || compact) && 'max-md:text-lg'"
            >
                <span
                    v-if="loading && !summary && type === 'scheduled'"
                    class="block h-8 w-28 max-w-full animate-pulse rounded bg-line"
                    aria-hidden="true"
                />
                <span v-else-if="summary && type === 'scheduled'">{{
                    formatHoursReportDuration(summary.scheduled_minutes)
                }}</span>
                <span
                    v-else
                    class="text-muted"
                    >{{ $t('team.reports.unavailable') }}</span
                >
                <HoursReportCost
                    v-if="summary && type !== 'unapproved'"
                    :cents="summary[`${type}_cost_cents`]"
                    :visible="employmentType !== 'volunteer'"
                    :missing-rate-count="
                        type === 'scheduled' ? summary.missing_rate_count : 0
                    "
                />
            </dd>
        </Card>
    </dl>
</template>
