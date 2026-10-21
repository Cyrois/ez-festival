<script setup>
import { trans } from 'laravel-vue-i18n';
import { formatHoursReportMoney } from '../../../lib/hoursReport';

defineProps({
    cents: { type: Number, default: null },
    visible: { type: Boolean, default: true },
    missingRateCount: { type: Number, default: 0 },
});
</script>

<template>
    <span
        v-if="visible"
        data-hours-cost
        class="block text-xs font-normal text-muted tabular-nums"
        >{{ formatHoursReportMoney(cents) }}</span
    >
    <span
        v-if="visible && missingRateCount > 0"
        class="block text-xs font-medium text-warning"
        >{{
            trans(
                missingRateCount === 1
                    ? 'team.reports.no_rate_one'
                    : 'team.reports.no_rate_many',
                { count: missingRateCount },
            )
        }}</span
    >
</template>
