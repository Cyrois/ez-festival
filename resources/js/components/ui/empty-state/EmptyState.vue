<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
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
        'flex flex-col items-center justify-center rounded-xl border border-dashed border-line bg-ground px-6 py-10 text-center',
        props.class,
    ),
);
</script>

<template>
    <div :class="classes">
        <div
            v-if="$slots.icon"
            class="mb-3 text-muted"
        >
            <slot name="icon" />
        </div>
        <h3
            v-if="title || $slots.title"
            class="m-0 mb-1.5 text-base font-bold text-charcoal"
        >
            <slot name="title">
                {{ title }}
            </slot>
        </h3>
        <p
            v-if="description || $slots.description"
            class="m-0 max-w-sm text-sm text-muted"
        >
            <slot name="description">
                {{ description }}
            </slot>
        </p>
        <div
            v-if="$slots.default"
            class="mt-4"
        >
            <slot />
        </div>
    </div>
</template>
