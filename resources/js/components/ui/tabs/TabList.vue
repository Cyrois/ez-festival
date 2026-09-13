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
    cn('flex flex-wrap gap-1 border-b border-line', props.class),
);
</script>

<template>
    <div
        role="tablist"
        :class="classes"
    >
        <slot />
    </div>
</template>
