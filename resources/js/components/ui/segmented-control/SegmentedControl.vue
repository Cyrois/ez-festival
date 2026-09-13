<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
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

const trackClass = computed(() =>
    cn(
        'inline-flex rounded-lg bg-page p-1',
        props.disabled && 'pointer-events-none opacity-45',
        props.class,
    ),
);

const optionClass = (optionValue) =>
    cn(
        'inline-flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-bold transition-colors',
        'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-primary/35',
        props.modelValue === optionValue
            ? 'bg-ground text-primary ring-1 ring-line'
            : 'bg-transparent text-muted hover:text-charcoal',
    );

const onSelect = (value) => {
    if (props.disabled) {
        return;
    }

    emit('update:modelValue', value);
};
</script>

<template>
    <div
        role="radiogroup"
        :class="trackClass"
        :aria-disabled="disabled ? 'true' : undefined"
    >
        <button
            v-for="option in options"
            :key="String(option.value)"
            type="button"
            role="radio"
            :aria-checked="modelValue === option.value ? 'true' : 'false'"
            :disabled="disabled"
            :class="optionClass(option.value)"
            @click="onSelect(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
