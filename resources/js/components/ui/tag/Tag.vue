<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';

const tokenClasses = {
    primary: 'border-primary/20 bg-primary-soft text-primary',
    secondary: 'border-secondary/20 bg-secondary-soft text-secondary',
    success: 'border-success/20 bg-success/10 text-success',
    warning: 'border-warning/20 bg-warning/10 text-warning',
    danger: 'border-danger/20 bg-danger/10 text-danger',
    neutral: 'border-line bg-page text-charcoal',
};

const tokenDot = {
    primary: 'bg-primary',
    secondary: 'bg-secondary',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    neutral: 'bg-muted',
};

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    color: {
        type: String,
        default: 'neutral',
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const isToken = computed(() =>
    Object.prototype.hasOwnProperty.call(tokenClasses, props.color),
);

const classes = computed(() =>
    cn(
        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-bold',
        isToken.value ? tokenClasses[props.color] : 'border-transparent',
        props.class,
    ),
);

const customStyle = computed(() => {
    if (isToken.value) {
        return undefined;
    }

    const color = props.color || '#6B7280';

    return {
        backgroundColor: `color-mix(in srgb, ${color} 16%, white)`,
        color,
        borderColor: `color-mix(in srgb, ${color} 28%, white)`,
    };
});

const dotClass = computed(() =>
    cn(
        'h-1.5 w-1.5 shrink-0 rounded-full',
        isToken.value && tokenDot[props.color],
    ),
);

const dotStyle = computed(() =>
    isToken.value ? undefined : { backgroundColor: props.color },
);
</script>

<template>
    <span
        :class="classes"
        :style="customStyle"
    >
        <span
            :class="dotClass"
            :style="dotStyle"
            aria-hidden="true"
        />
        {{ name }}
    </span>
</template>
