<script setup>
import { computed, useAttrs } from 'vue';
import { cn } from '../../../lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean],
        default: '',
    },
    value: {
        type: [String, Number, Boolean],
        required: true,
    },
    label: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
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

const boxClass = computed(() =>
    cn(
        'h-4 w-4 shrink-0 rounded-full border border-line bg-ground text-primary accent-primary focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-primary/35 disabled:cursor-not-allowed',
        props.invalid && 'border-danger focus-visible:ring-danger/35',
        props.class,
    ),
);

const onChange = () => {
    emit('update:modelValue', props.value);
};
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
            type="radio"
            :value="value"
            :checked="modelValue === value"
            :disabled="disabled"
            :class="boxClass"
            :aria-invalid="invalid ? 'true' : undefined"
            v-bind="attrs"
            @change="onChange"
        />
        <span v-if="label || $slots.default">
            <slot>{{ label }}</slot>
        </span>
    </label>
</template>
