<script setup>
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Icon } from '../ui/icon';
const props = defineProps({
    count: { type: Number, required: true },
    level: { type: Number, default: null },
});
const low = computed(() => props.level !== null && props.count <= props.level);
const warning = computed(() =>
    trans('inventory.low_warning', { count: props.level }),
);
</script>

<template>
    <span class="inline-flex items-center justify-end gap-2 tabular-nums">
        <span
            v-if="low"
            :title="warning"
            class="inline-flex text-warning"
        >
            <Icon :name="['fas', 'triangle-exclamation']" />
            <span class="sr-only">{{ warning }}</span>
        </span>
        <span :class="count === 0 && 'text-muted'">{{ count }}</span>
    </span>
</template>
