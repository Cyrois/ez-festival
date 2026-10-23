<script setup>
import { mealReportCounts, mealReportCountLabel } from '../../lib/mealReport';

defineProps({ counts: { type: Object, required: true } });
</script>

<template>
    <dl class="grid min-w-0 grid-cols-3 gap-x-2 gap-y-3 text-sm">
        <div
            v-for="key in mealReportCounts"
            :key="key"
            class="min-w-0"
        >
            <dt class="text-xs break-words text-muted">
                {{ $t(`reports.meals.columns.${key}`) }}
            </dt>
            <dd class="mt-0.5 font-semibold break-words tabular-nums">
                {{ mealReportCountLabel(counts, key) }}
            </dd>
        </div>
    </dl>
</template>
