<script setup>
import { computed, ref, watch } from 'vue';
import { cva } from 'class-variance-authority';
import { cn } from '../../../lib/utils';

const props = defineProps({
    name: {
        type: String,
        default: '',
    },
    src: {
        type: String,
        default: '',
    },
    alt: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'md',
    },
    tone: {
        type: String,
        default: 'primary',
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const failed = ref(false);

watch(
    () => props.src,
    () => {
        failed.value = false;
    },
);

const avatarVariants = cva(
    'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-sans font-bold select-none',
    {
        variants: {
            size: {
                sm: 'h-8 w-8 text-xs',
                md: 'h-10 w-10 text-sm',
                lg: 'h-12 w-12 text-base',
            },
            tone: {
                primary: 'bg-primary-soft text-primary',
                neutral: 'bg-page text-charcoal',
            },
        },
        defaultVariants: {
            size: 'md',
            tone: 'primary',
        },
    },
);

const initials = computed(() => {
    const trimmed = (props.name || '').trim();

    if (!trimmed) {
        return '?';
    }

    const parts = trimmed.split(/\s+/).filter(Boolean);

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0] ?? ''}${parts[parts.length - 1][0] ?? ''}`.toUpperCase();
});

const classes = computed(() =>
    cn(
        avatarVariants({
            size: props.size,
            tone: props.tone,
        }),
        props.class,
    ),
);

const imageAlt = computed(() => props.alt || props.name || '');
const showImage = computed(() => Boolean(props.src) && !failed.value);

const onError = () => {
    failed.value = true;
};
</script>

<template>
    <span
        :class="classes"
        :role="showImage ? undefined : 'img'"
        :aria-label="showImage ? undefined : imageAlt || undefined"
    >
        <img
            v-if="showImage"
            :src="src"
            :alt="imageAlt"
            class="h-full w-full object-cover"
            @error="onError"
        />
        <span
            v-else
            aria-hidden="true"
        >
            {{ initials }}
        </span>
    </span>
</template>
