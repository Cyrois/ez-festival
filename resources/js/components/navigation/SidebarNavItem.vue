<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cn } from '../../lib/utils';
import { Icon } from '../ui/icon';

const props = defineProps({
    href: {
        type: String,
        default: '',
    },
    enabled: {
        type: Boolean,
        default: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
    icon: {
        type: [String, Array, Object],
        default: null,
    },
    iconOnly: {
        type: Boolean,
        default: false,
    },
    iconDesktopOnly: {
        type: Boolean,
        default: false,
    },
    density: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'settings', 'sub'].includes(value),
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
    ariaCurrent: {
        type: String,
        default: undefined,
    },
});

const tag = computed(() => (props.enabled && props.href ? Link : 'span'));

const densityClass = computed(() => {
    if (props.density === 'sub') {
        return 'min-h-9 gap-2 px-3 py-2 text-[12px] lg:h-7 lg:min-h-7 lg:py-1';
    }

    if (props.density === 'settings') {
        return 'min-h-11 gap-2 px-2 py-2.5 text-[13px]';
    }

    return 'min-h-11 gap-2.5 px-3 py-2.5 text-[13px] lg:h-8 lg:min-h-8 lg:py-1.5';
});

const stateClass = computed(() => {
    if (!props.enabled) {
        return 'cursor-default text-charcoal/35';
    }

    if (props.active) {
        return 'bg-primary/10 text-primary';
    }

    const textClass =
        props.density === 'sub' ? 'text-charcoal/70' : 'text-charcoal/80';

    return `${textClass} hover:bg-page hover:text-charcoal`;
});

const classes = computed(() =>
    cn(
        'inline-flex w-full items-center overflow-hidden rounded-lg font-semibold no-underline transition-[padding,gap,background-color,color] duration-200 ease-in-out',
        densityClass.value,
        props.iconOnly && 'lg:justify-center lg:gap-0 lg:px-0',
        stateClass.value,
        props.class,
    ),
);
</script>

<template>
    <component
        :is="tag"
        :href="enabled && href ? href : undefined"
        :class="classes"
        :aria-current="ariaCurrent"
        :aria-disabled="enabled ? undefined : 'true'"
    >
        <Icon
            v-if="icon"
            :name="icon"
            size="sm"
            fixed-width
            :class="iconDesktopOnly ? 'hidden lg:inline-block' : ''"
        />
        <span
            class="min-w-0 truncate overflow-hidden whitespace-nowrap transition-[max-width,opacity] duration-150 ease-out"
            :class="
                iconOnly
                    ? 'lg:max-w-0 lg:opacity-0'
                    : 'lg:max-w-48 lg:opacity-100'
            "
        >
            <slot />
        </span>
    </component>
</template>
