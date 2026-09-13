<script setup>
import { computed, useAttrs } from 'vue';
import { cn } from '../../../lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    invalid: {
        type: Boolean,
        default: false,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const attrs = useAttrs();

const classes = computed(() =>
    cn(
        'box-border h-10 w-full appearance-none rounded-lg border border-line bg-ground px-3 pr-9 text-sm text-charcoal outline-none transition-shadow focus:border-primary focus:shadow-[0_0_0_3px_color-mix(in_srgb,var(--color-primary)_22%,transparent)] disabled:cursor-not-allowed disabled:opacity-45',
        props.invalid &&
            'border-danger focus:border-danger focus:shadow-[0_0_0_3px_color-mix(in_srgb,var(--color-danger)_22%,transparent)]',
        props.class,
    ),
);

const onChange = (event) => {
    emit('update:modelValue', event.target.value);
};
</script>

<template>
    <div class="relative w-full">
        <select
            :value="modelValue"
            :class="classes"
            :aria-invalid="invalid ? 'true' : undefined"
            v-bind="attrs"
            @change="onChange"
        >
            <slot />
        </select>
        <span
            class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-muted"
            aria-hidden="true"
        >
            ▾
        </span>
    </div>
</template>
