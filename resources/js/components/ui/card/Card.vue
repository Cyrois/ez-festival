<script setup>
import { computed } from 'vue';
import CardTitle from './CardTitle.vue';
import { cn } from '../../../lib/utils';

const props = defineProps({
    title: {
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
        'rounded-xl border border-line bg-ground px-5 py-5 text-charcoal',
        props.class,
    ),
);
</script>

<template>
    <div :class="classes">
        <div
            v-if="title || $slots.header"
            class="mb-4"
        >
            <slot name="header">
                <CardTitle v-if="title">
                    {{ title }}
                </CardTitle>
            </slot>
        </div>
        <div>
            <slot />
        </div>
        <div
            v-if="$slots.footer"
            class="mt-4 border-t border-line pt-4"
        >
            <slot name="footer" />
        </div>
    </div>
</template>
