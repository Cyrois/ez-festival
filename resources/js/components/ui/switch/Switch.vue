<script setup>
import { computed, useAttrs } from 'vue';
import { cn } from '../../../lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        default: '',
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

const emit = defineEmits(['update:modelValue']);

const attrs = useAttrs();

const onChange = (event) => {
    emit('update:modelValue', event.target.checked);
};

const trackClass = computed(() =>
    cn(
        'relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors',
        'peer-focus-visible:outline-none peer-focus-visible:ring-[3px] peer-focus-visible:ring-primary/35',
        props.modelValue ? 'bg-primary' : 'bg-line',
        props.class,
    ),
);
</script>

<template>
    <label
        :class="
            cn(
                'inline-flex items-center gap-2 text-sm text-charcoal',
                disabled && 'cursor-not-allowed opacity-45',
            )
        "
    >
        <input
            type="checkbox"
            role="switch"
            class="peer sr-only"
            :checked="modelValue"
            :disabled="disabled"
            v-bind="attrs"
            @change="onChange"
        />
        <span
            :class="trackClass"
            aria-hidden="true"
        >
            <span
                :class="
                    cn(
                        'absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow-sm transition-transform',
                        modelValue && 'translate-x-4',
                    )
                "
            />
        </span>
        <span v-if="label || $slots.default">
            <slot>{{ label }}</slot>
        </span>
    </label>
</template>
