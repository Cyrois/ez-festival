<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';

const props = defineProps({
    selected: {
        type: Boolean,
        default: false,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const classes = computed(() =>
    cn(
        'border-b border-line last:border-b-0 transition-colors hover:bg-page',
        props.selected && 'bg-secondary-soft hover:bg-secondary-soft',
        props.class,
    ),
);
</script>

<template>
    <tr :class="classes">
        <slot />
    </tr>
</template>
