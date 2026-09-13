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
        'w-full overflow-hidden rounded-xl border border-line bg-ground',
        props.class,
    ),
);
</script>

<template>
    <div :class="classes">
        <div class="w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <slot />
            </table>
        </div>
    </div>
</template>
