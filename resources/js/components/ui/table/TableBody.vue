<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';

const props = defineProps({
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const classes = computed(() => cn(props.class));
</script>

<template>
    <tbody :class="classes">
        <slot />
    </tbody>
</template>
