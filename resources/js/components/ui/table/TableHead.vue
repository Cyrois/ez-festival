<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';

const props = defineProps({
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const classes = computed(() =>
    cn(
        'bg-page px-4 py-3 text-xs font-bold tracking-wide text-muted uppercase',
        props.class,
    ),
);
</script>

<template>
    <th :class="classes">
        <slot />
    </th>
</template>
