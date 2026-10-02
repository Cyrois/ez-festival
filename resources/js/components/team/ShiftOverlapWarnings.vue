<script setup>
import { trans } from 'laravel-vue-i18n';
import { Icon } from '../ui/icon';
defineProps({ overlaps: { type: Array, default: () => [] } });
</script>

<template>
    <p
        v-for="warning in overlaps"
        :key="warning.shift_id"
        class="mt-1 flex items-start gap-2 text-xs text-warning"
    >
        <Icon :name="['fas', 'circle-info']" />
        {{
            trans('team.scheduling.assignments.overlap', {
                name: warning.shift_name,
                minutes: warning.overlap_minutes,
                start: warning.starts_at.replace('T', ' '),
                end: warning.ends_at.replace('T', ' '),
            })
        }}
    </p>
</template>
