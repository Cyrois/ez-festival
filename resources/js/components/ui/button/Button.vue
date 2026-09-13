<script setup>
import { computed } from 'vue';
import { cva } from 'class-variance-authority';
import { cn } from '../../../lib/utils';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
    },
    size: {
        type: String,
        default: 'md',
    },
    type: {
        type: String,
        default: 'button',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 rounded-lg border border-transparent font-sans font-bold whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-primary/35 disabled:pointer-events-none disabled:opacity-45 cursor-pointer',
    {
        variants: {
            variant: {
                primary: 'bg-primary text-white hover:bg-primary-hover',
                secondary:
                    'border-line bg-transparent text-charcoal hover:bg-page',
                soft: 'bg-secondary-soft text-secondary hover:bg-secondary-soft/80',
                ghost: 'bg-transparent text-primary hover:bg-primary-soft',
                danger: 'bg-danger text-white hover:bg-danger/90',
                'outline-danger':
                    'border-danger/30 bg-transparent text-danger hover:bg-danger/5',
            },
            size: {
                sm: 'h-8 px-3 text-[13px]',
                md: 'h-10 px-4 text-sm',
                lg: 'h-12 px-5 text-[15px]',
                icon: 'h-10 w-10 p-0',
            },
        },
        defaultVariants: {
            variant: 'primary',
            size: 'md',
        },
    },
);

const classes = computed(() =>
    cn(
        buttonVariants({
            variant: props.variant,
            size: props.size,
        }),
        props.class,
    ),
);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        :class="classes"
    >
        <slot />
    </button>
</template>
