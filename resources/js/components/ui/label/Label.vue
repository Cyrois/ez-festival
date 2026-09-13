<script setup>
import { cn } from '../../../lib/utils';

const props = defineProps({
    htmlFor: {
        type: String,
        default: '',
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});
</script>

<template>
    <label
        :for="htmlFor || undefined"
        :class="cn('text-xs font-bold text-charcoal', props.class)"
    >
        <slot />
    </label>
</template>
