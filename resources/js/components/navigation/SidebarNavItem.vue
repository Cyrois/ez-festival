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
        return 'min-h-9 gap-2 px-3 py-2 text-[12px]';
    }

    if (props.density === 'settings') {
        return 'min-h-11 gap-2 px-2 py-2.5 text-[13px]';
    }

    return 'min-h-11 gap-2.5 px-3 py-2.5 text-[13px]';
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
        'inline-flex w-full items-center rounded-lg font-semibold no-underline',
        densityClass.value,
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
        />
        <slot />
    </component>
</template>
