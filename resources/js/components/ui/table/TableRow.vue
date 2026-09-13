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
    cn('border-b border-line last:border-b-0', props.class),
);
</script>

<template>
    <tr :class="classes">
        <slot />
    </tr>
</template>
