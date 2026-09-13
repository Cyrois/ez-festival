<script setup>
import { computed } from 'vue';
import { cva } from 'class-variance-authority';
import { cn } from '../../../lib/utils';

const props = defineProps({
    variant: {
        type: String,
        default: 'neutral',
    },
    pill: {
        type: Boolean,
        default: false,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const badgeVariants = cva(
    'inline-flex items-center border font-sans text-xs font-bold whitespace-nowrap',
    {
        variants: {
            variant: {
                neutral: 'border-line bg-page text-charcoal',
                primary: 'border-primary/20 bg-primary-soft text-primary',
                success: 'border-success/20 bg-success/10 text-success',
                warning: 'border-warning/20 bg-warning/10 text-warning',
                danger: 'border-danger/20 bg-danger/10 text-danger',
                outline: 'border-line bg-transparent text-charcoal',
            },
            pill: {
                true: 'rounded-full px-2.5 py-0.5',
                false: 'rounded-lg px-2 py-0.5',
            },
        },
        defaultVariants: {
            variant: 'neutral',
            pill: false,
        },
    },
);

const classes = computed(() =>
    cn(
        badgeVariants({
            variant: props.variant,
            pill: props.pill,
        }),
        props.class,
    ),
);
</script>

<template>
    <span :class="classes">
        <slot />
    </span>
</template>
