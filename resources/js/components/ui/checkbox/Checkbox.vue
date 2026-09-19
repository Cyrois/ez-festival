<script setup>
import { computed, useAttrs } from 'vue';
import { cn } from '../../../lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: [Boolean, Array],
        default: false,
    },
    value: {
        type: [String, Number],
        default: undefined,
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
        'h-4 w-4 shrink-0 rounded-[4px] border border-line bg-ground text-primary accent-primary focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-primary/35 disabled:cursor-not-allowed',
        props.invalid && 'border-danger focus-visible:ring-danger/35',
        props.class,
    ),
);

const isChecked = computed(() =>
    Array.isArray(props.modelValue)
        ? props.modelValue.includes(props.value)
        : props.modelValue,
);

const onChange = (event) => {
    if (!Array.isArray(props.modelValue)) {
        emit('update:modelValue', event.target.checked);

        return;
    }

    emit(
        'update:modelValue',
        event.target.checked
            ? [...props.modelValue, props.value]
            : props.modelValue.filter((value) => value !== props.value),
    );
};
</script>

<template>
    <label
        :class="
            cn(
                'inline-flex cursor-pointer items-center gap-2 text-sm text-charcoal',
                disabled && 'cursor-not-allowed opacity-45',
            )
        "
    >
        <input
            type="checkbox"
            :checked="isChecked"
            :disabled="disabled"
            :class="boxClass"
            :aria-invalid="invalid ? 'true' : undefined"
            :value="value"
            v-bind="attrs"
            @change="onChange"
        />
        <span
            v-if="label || $slots.default"
            class="flex items-center"
        >
            <slot>{{ label }}</slot>
        </span>
    </label>
</template>
