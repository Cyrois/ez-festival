<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';

const props = defineProps({
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const classes = computed(() => cn('mb-4', props.class));
</script>

<template>
    <div :class="classes">
        <slot />
    </div>
</template>
