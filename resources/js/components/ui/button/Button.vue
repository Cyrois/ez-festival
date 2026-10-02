<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cn } from '../../../lib/utils';
import { buttonVariants } from './buttonVariants';

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
    loading: {
        type: Boolean,
        default: false,
    },
    href: {
        type: String,
        default: '',
    },
    as: {
        type: [String, Object],
        default: null,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const isDisabled = computed(() => props.disabled || props.loading);

const tag = computed(() => {
    if (props.as) {
        return props.as;
    }

    if (props.href) {
        return Link;
    }

    return 'button';
});

const isNativeButton = computed(() => tag.value === 'button');

const classes = computed(() =>
    cn(
        buttonVariants({
            variant: props.variant,
            size: props.size,
        }),
        isDisabled.value &&
            !isNativeButton.value &&
            'cursor-not-allowed opacity-45',
        props.class,
    ),
);

const onClick = (event) => {
    if (isDisabled.value && !isNativeButton.value) {
        event.preventDefault();
        event.stopPropagation();
    }
};
</script>

<template>
    <component
        :is="tag"
        :href="href || undefined"
        :type="isNativeButton ? type : undefined"
        :disabled="isNativeButton ? isDisabled : undefined"
        :aria-disabled="isDisabled ? 'true' : undefined"
        :tabindex="isDisabled && !isNativeButton ? -1 : undefined"
        :aria-busy="loading ? 'true' : undefined"
        :class="classes"
        @click="onClick"
    >
        <slot />
    </component>
</template>
