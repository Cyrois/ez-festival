<script setup>
import { Card } from '../../ui/card';
import { formatHoursReportDuration } from '../../../lib/hoursReport';

defineProps({
    summary: { type: Object, default: null },
    showingPeople: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});
</script>

<template>
    <dl
        class="grid gap-3"
        :class="showingPeople ? 'grid-cols-3' : 'md:grid-cols-3'"
    >
        <Card
            v-for="type in ['scheduled', 'approved', 'unapproved']"
            :key="type"
            :class="showingPeople && 'max-md:px-2.5 max-md:py-3'"
        >
            <dt
                class="text-xs font-semibold text-muted uppercase"
                :class="showingPeople && 'max-md:text-[10px]'"
            >
                {{ $t(`team.reports.${type}`) }}
            </dt>
            <dd
                class="mt-1 text-2xl font-bold tabular-nums"
                :class="showingPeople && 'max-md:text-lg'"
            >
                <span
                    v-if="loading && !summary && type === 'scheduled'"
                    class="block h-8 w-28 animate-pulse rounded bg-line"
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
            </dd>
        </Card>
    </dl>
</template>
