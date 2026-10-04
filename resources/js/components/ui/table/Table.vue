<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';

const props = defineProps({
    scrollLabel: {
        type: String,
        default: '',
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const classes = computed(() =>
    cn(
        'w-full overflow-hidden rounded-xl border border-line bg-ground',
        props.class,
    ),
);
</script>

<template>
    <div :class="classes">
        <div
            class="w-full overflow-x-auto"
            :tabindex="scrollLabel ? 0 : undefined"
            :role="scrollLabel ? 'region' : undefined"
            :aria-label="scrollLabel || undefined"
        >
            <table class="w-full border-collapse text-left">
                <slot />
            </table>
        </div>
    </div>
</template>
