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
    cn('px-4 py-3 text-sm text-charcoal', props.class),
);
</script>

<template>
    <td :class="classes">
        <slot />
    </td>
</template>
