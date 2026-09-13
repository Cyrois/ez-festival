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
        'box-border h-10 w-full rounded-lg border border-line bg-ground px-3 text-sm text-charcoal outline-none transition-[box-shadow,border-color] placeholder:text-muted focus:border-primary focus:ring-[3px] focus:ring-primary/35 disabled:cursor-not-allowed disabled:opacity-45',
        props.invalid &&
            'border-danger bg-danger/5 focus:border-danger focus:ring-danger/35',
        props.class,
    ),
);

const onInput = (event) => {
    emit('update:modelValue', event.target.value);
};
</script>

<template>
    <input
        :value="modelValue"
        :class="classes"
        :aria-invalid="invalid ? 'true' : undefined"
        v-bind="attrs"
        @input="onInput"
    />
</template>
